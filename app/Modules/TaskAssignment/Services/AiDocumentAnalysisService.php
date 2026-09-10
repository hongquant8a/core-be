<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Phân tích văn bản hành chính bằng AI (DeepSeek) → metadata văn bản + đầu việc nháp.
 *
 * Trước 08/09/2026 phần này nằm ở frontend, gọi thẳng api.deepseek.com qua proxy
 * `/ai` của Vite. Proxy đó gắn sẵn Authorization nhưng KHÔNG kiểm tra đăng nhập —
 * ai chạm được cổng dev server là dùng được API key. Chuyển về đây để key nằm
 * trên server, endpoint đi qua auth + permission + throttle như mọi API khác.
 *
 * Cấu hình đọc theo thứ tự: bảng `settings` (màn Cài đặt chung) → config/env.
 */
class AiDocumentAnalysisService
{
    /** Trần output của deepseek-chat. Vượt mức này JSON bị cắt giữa chừng. */
    protected const MAX_TOKENS = 8192;

    /** Trích xuất phải bám sát văn bản, không "sáng tác". */
    protected const TEMPERATURE = 0;

    /**
     * Văn bản thật mất 5-35s để phân tích (đã đo). 60s cho một lượt, tối đa 2
     * lượt → xấu nhất ~121s, vẫn nằm trong ngưỡng chờ của một request web.
     */
    protected const TIMEOUT_SECONDS = 60;

    /** Retry cho lỗi tạm thời (429, 5xx, rớt mạng). */
    protected const MAX_RETRIES = 2;

    protected const RETRY_DELAY_MS = 1000;

    protected const SYSTEM_PROMPT = <<<'PROMPT'
Bạn là một trợ lý AI chuyên gia về xử lý văn bản hành chính Việt Nam. Nhiệm vụ của bạn là trích xuất thông tin từ các "Thông báo kết luận cuộc họp" thành định dạng JSON chuẩn.

### 1. QUY TẮC TRÍCH XUẤT TIÊU ĐỀ (TITLE)
* Tìm đoạn văn bản bắt đầu bằng "THÔNG BÁO" hoặc "KẾT LUẬN".
* Nối các dòng bị ngắt quãng thành một câu tiêu đề hoàn chỉnh.

### 2. QUY TẮC TRÍCH XUẤT NGÀY VĂN BẢN (DATE)
* Tìm ngày ban hành của văn bản.
* **BẮT BUỘC:** Chuyển đổi sang định dạng **dd/mm/yyyy** (Ví dụ: "07/07/2025").
* **QUAN TRỌNG:** Nếu văn bản KHÔNG có ngày ban hành (hoặc để trống dạng "ngày ... tháng ... năm ..."), trả về `null`. TUYỆT ĐỐI KHÔNG được suy đoán, không lấy ngày hôm nay, không bịa ra ngày.

### 3. QUY TẮC TRÍCH XUẤT LOẠI VĂN BẢN (DOCUMENT_TYPE)
* Xác định loại văn bản, chỉ chọn MỘT trong các giá trị: "THÔNG BÁO", "KẾT LUẬN", "CÔNG VĂN", "QUYẾT ĐỊNH", "KẾ HOẠCH", "BÁO CÁO".
* Không xác định được thì trả về `null`.

### 4. QUY TẮC TRÍCH XUẤT TÓM TẮT (SUMMARY)
* Tóm tắt ngắn gọn trong 1-2 câu: Ai chủ trì? Nội dung chính là gì?

### 5. QUY TẮC TRÍCH XUẤT CÔNG VIỆC (TASKS) - QUAN TRỌNG
Duyệt qua từng mục để xác định các trường sau cho mỗi công việc:

* **assignee:** Đơn vị/người **chủ trì** thực hiện (đứng ngay sau "Giao", "Giao cho"). CHỈ ghi tên đơn vị, không kèm động từ.
* **coordinators:** Mảng các đơn vị **phối hợp** (đứng sau "phối hợp với", "cùng với", "phối hợp cùng"). Không có thì trả về mảng rỗng `[]`.
* **content:** Nội dung công việc, viết thành câu hoàn chỉnh và **VIẾT HOA chữ cái đầu**. Không lặp lại tên đơn vị thực hiện ở đầu câu.
* **priority:** Mức độ ưu tiên, chỉ chọn MỘT trong: "urgent", "high", "medium", "low".
   * "urgent": có từ "hỏa tốc", "khẩn cấp", "ngay lập tức".
   * "high": có từ "khẩn trương", "gấp", "sớm", "ưu tiên".
   * "low": có từ "khi có điều kiện", "về lâu dài".
   * Còn lại: "medium".
* **deadline:**
   * Nếu tìm thấy ngày cụ thể: Chuyển về định dạng **dd/mm/yyyy**. (Tự động ghép năm hoặc lấy ngày cuối tháng nếu thiếu thông tin).
   * Nếu là công việc định kỳ (hằng tuần/tháng) hoặc không có hạn: Trả về `null`.
* **has_deadline (Boolean):**
   * Trả về `true` nếu trường `deadline` có giá trị ngày tháng cụ thể.
   * Trả về `false` nếu trường `deadline` là `null` (bao gồm cả việc định kỳ hoặc không thời hạn).

### 6. QUY TẮC ĐỊNH DẠNG ĐẦU RA (QUAN TRỌNG NHẤT)
* **TUYỆT ĐỐI KHÔNG** sử dụng thẻ markdown code block (tức là KHÔNG ĐƯỢC viết ```json hoặc ```).
* **CHỈ TRẢ VỀ** chuỗi JSON thuần túy (Raw String).
* Bắt đầu ngay lập tức bằng dấu `{` và kết thúc bằng dấu `}`.

Cấu trúc mẫu bắt buộc:
{
 "title": "...",
 "date": "dd/mm/yyyy hoặc null",
 "document_type": "THÔNG BÁO",
 "summary": "...",
 "tasks": [
   {
     "assignee": "Sở Tài chính",
     "coordinators": ["Sở Kế hoạch và Đầu tư"],
     "content": "Tổng hợp báo cáo tiến độ giải ngân của các đơn vị",
     "priority": "high",
     "deadline": "15/07/2025",
     "has_deadline": true
   },
   {
     "assignee": "Phòng B",
     "coordinators": [],
     "content": "Họp giao ban hằng tuần",
     "priority": "medium",
     "deadline": null,
     "has_deadline": false
   }
 ]
}
PROMPT;

    /**
     * @return array{title: string, date: string|null, document_type: string|null, summary: string, tasks: array<int, array<string, mixed>>}
     *
     * @throws RuntimeException Mã lỗi HTTP tương ứng nằm ở code: 503 thiếu cấu hình, 502 lỗi phía DeepSeek.
     */
    public function analyze(string $content): array
    {
        [$baseUrl, $token, $model] = $this->config();

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($token)
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(10)
                ->retry(self::MAX_RETRIES, self::RETRY_DELAY_MS, function ($exception) {
                    // Chỉ retry lỗi tạm thời. Sai key (401) hay hết credit (402)
                    // thì thử lại cũng vậy, chỉ tốn thêm thời gian chờ của user.
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    $status = $exception instanceof RequestException ? $exception->response->status() : 0;

                    return $status === 429 || $status >= 500;
                }, throw: false)
                ->post('/chat/completions', [
                    'model' => $model,
                    'temperature' => self::TEMPERATURE,
                    'max_tokens' => self::MAX_TOKENS,
                    // JSON mode: DeepSeek đảm bảo trả JSON hợp lệ, khỏi lo model bọc
                    // markdown fence. (Yêu cầu prompt phải chứa chữ "JSON" — có rồi.)
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $content],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('AI analyze: không kết nối được DeepSeek', ['message' => $e->getMessage()]);

            throw new RuntimeException('Không kết nối được dịch vụ AI. Vui lòng thử lại sau.', 502);
        }

        if (! $response->successful()) {
            // Body của DeepSeek nói rõ nguyên nhân (sai key, hết credit) — ghi log
            // để quản trị lần ra, nhưng không trả nguyên văn về client.
            Log::warning('AI analyze: DeepSeek trả lỗi', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            throw new RuntimeException('Dịch vụ AI trả về lỗi ('.$response->status().'). Vui lòng thử lại sau.', 502);
        }

        return $this->parse($response->json());
    }

    /** @return array{0: string, 1: string, 2: string} [baseUrl, token, model] */
    protected function config(): array
    {
        $baseUrl = rtrim((string) (Setting::get('api_deepseek_url') ?: config('services.deepseek.url')), '/');
        $token = (string) (Setting::get('api_deepseek_token') ?: config('services.deepseek.token'));

        if (! $baseUrl || ! $token) {
            throw new RuntimeException('Chưa cấu hình DeepSeek (URL/Token) trong Cài đặt chung.', 503);
        }

        return [$baseUrl, $token, (string) config('services.deepseek.model')];
    }

    /**
     * Envelope chuẩn OpenAI: { choices: [{ message: { content: "<chuỗi JSON>" }, finish_reason }] }.
     * Bóc `content` rồi parse ra shape { title, date, document_type, summary, tasks }.
     */
    protected function parse(?array $envelope): array
    {
        $choice = $envelope['choices'][0] ?? null;
        $content = $choice['message']['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Dịch vụ AI trả về nội dung rỗng. Vui lòng thử lại.', 502);
        }

        // Vượt max_tokens → JSON bị cắt giữa chừng. Báo rõ thay vì để json_decode
        // ném lỗi cú pháp khó lần ra nguyên nhân.
        if (($choice['finish_reason'] ?? null) === 'length') {
            throw new RuntimeException('Văn bản quá dài, kết quả phân tích bị cắt. Vui lòng chia nhỏ văn bản.', 422);
        }

        // Gỡ markdown fence phòng khi model bỏ qua chỉ dẫn không dùng code block.
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
        $data = json_decode((string) $cleaned, true);

        if (! is_array($data)) {
            Log::warning('AI analyze: content không phải JSON', ['content' => mb_substr($content, 0, 500)]);

            throw new RuntimeException('Kết quả phân tích không đúng định dạng. Vui lòng thử lại.', 502);
        }

        return [
            'title' => (string) ($data['title'] ?? ''),
            // Prompt trả null khi văn bản không ghi ngày — giữ nguyên null, không
            // suy đoán ngày hôm nay.
            'date' => $data['date'] ?? null,
            'document_type' => $data['document_type'] ?? null,
            'summary' => (string) ($data['summary'] ?? ''),
            'tasks' => is_array($data['tasks'] ?? null) ? array_values($data['tasks']) : [],
        ];
    }
}
