<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserProfile;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Liên kết tài khoản nhân viên với Telegram bằng deep link.
 *
 * Luồng: nhân viên bấm "Liên kết" → sinh token → bấm link/quét QR → Telegram mở chat bot
 * và gửi `/start <token>` → webhook đẩy vào job → job gọi handleStart() lưu chat_id.
 *
 * Vì sao không cho nhập chat_id tay ở luồng chính: nhập nhầm một chữ số là thông báo nội bộ
 * bay sang người lạ. Ô nhập tay chỉ còn ở màn quản trị, có cảnh báo.
 */
class TelegramLinkService
{
    /** Token sống 24 giờ — đủ cho người nhận link rồi mở Telegram sau. */
    private const TOKEN_TTL_HOURS = 24;

    public function __construct(
        private SettingService $settings,
        private NotificationService $notifier,
    ) {}

    /**
     * Trạng thái liên kết của một tài khoản — dữ liệu cho giao diện cá nhân.
     */
    public function status(User $user): array
    {
        $profile = $this->profileOf($user);
        $expiresAt = $profile?->telegram_token_expires_at;
        $pending = (bool) $profile?->telegram_link_token && $expiresAt?->isFuture();

        return [
            'linked' => (bool) $profile?->telegram_chat_id,
            'linked_at' => $profile?->telegram_linked_at?->format('H:i:s d/m/Y'),
            'pending_link' => $pending,
            'expires_at' => $pending ? $expiresAt->format('H:i:s d/m/Y') : null,
            // Đọc thẳng cấu hình, không hỏi Telegram ở đây: trang cá nhân gọi endpoint này
            // mỗi lần mở và cứ 5 giây một lần khi đang chờ quét mã — một lần getMe hỏng là
            // người dùng ngồi nhìn thẻ quay 30 giây. getMe chỉ chạy lúc tạo đường dẫn.
            'bot_username' => $this->settings->getByKey('tg_bot_username')['value'] ?? null,
        ];
    }

    /**
     * Sinh token mới + deep link. Mỗi lần gọi vô hiệu hoá token cũ.
     */
    public function createLink(User $user): array
    {
        $botUsername = $this->botUsername();

        if (! $botUsername) {
            throw new RuntimeException('Chưa cấu hình bot Telegram. Liên hệ quản trị hệ thống.');
        }

        // 32 ký tự an toàn URL, dưới giới hạn 64 ký tự của payload start.
        $token = Str::random(32);
        $expiresAt = now()->addHours(self::TOKEN_TTL_HOURS);

        UserProfile::firstOrCreate(['user_id' => $user->id])->update([
            'telegram_link_token' => $token,
            'telegram_token_expires_at' => $expiresAt,
        ]);

        return [
            'deep_link' => "https://t.me/{$botUsername}?start={$token}",
            'expires_at' => $expiresAt->format('H:i:s d/m/Y'),
        ];
    }

    /**
     * Nhân viên tự hủy liên kết từ giao diện cá nhân.
     */
    public function unlink(User $user): void
    {
        $this->profileOf($user)?->update([
            'telegram_chat_id' => null,
            'telegram_link_token' => null,
            'telegram_token_expires_at' => null,
            'telegram_linked_at' => null,
        ]);
    }

    /**
     * Gửi tin thử tới chính tài khoản đang đăng nhập — xác minh liên kết còn sống.
     */
    public function sendTest(User $user): SendResult
    {
        $chatId = $this->profileOf($user)?->telegram_chat_id;

        if (! $chatId) {
            throw new RuntimeException('Tài khoản chưa liên kết Telegram.');
        }

        return $this->sendMessage($chatId, '<b>Tin nhắn kiểm thử</b>'."\n\n".'Kênh Telegram của bạn đang hoạt động bình thường.');
    }

    /**
     * Xử lý lệnh /start từ webhook. Chạy trong job nên không có user đăng nhập, không có
     * ngữ cảnh tổ chức — mọi truy vấn đi thẳng từ token/chat_id.
     *
     * Mọi nhánh đều phản hồi lại Telegram: im lặng thì người dùng đứng nhìn màn hình trống,
     * không biết đã liên kết được hay chưa.
     */
    public function handleStart(string $chatId, ?string $token): void
    {
        if (! $token) {
            $this->sendMessage($chatId, 'Vui lòng mở phần <b>Thông báo Telegram</b> trong ứng dụng và bấm "Liên kết Telegram" để lấy đường dẫn liên kết.');

            return;
        }

        $profile = UserProfile::where('telegram_link_token', $token)->first();

        if (! $profile) {
            $this->sendMessage($chatId, 'Đường dẫn liên kết không hợp lệ hoặc đã được dùng. Vào ứng dụng tạo lại đường dẫn mới rồi thử lại.');

            return;
        }

        if ($profile->telegram_token_expires_at?->isPast()) {
            $this->sendMessage($chatId, 'Đường dẫn liên kết đã hết hạn. Vào ứng dụng tạo lại đường dẫn mới rồi thử lại.');

            return;
        }

        // Cùng một tài khoản Telegram đổi sang người khác (máy dùng chung, bàn giao việc):
        // gỡ khỏi chủ cũ trước, nếu không hai người cùng nhận thông báo của nhau.
        UserProfile::where('telegram_chat_id', $chatId)
            ->where('user_id', '!=', $profile->user_id)
            ->update(['telegram_chat_id' => null, 'telegram_linked_at' => null]);

        $profile->update([
            'telegram_chat_id' => $chatId,
            'telegram_link_token' => null,
            'telegram_token_expires_at' => null,
            'telegram_linked_at' => now(),
        ]);

        $name = User::find($profile->user_id)?->name;

        $this->sendMessage(
            $chatId,
            '<b>Liên kết thành công</b>'."\n\n"
            .($name ? "Tài khoản: {$name}\n" : '')
            .'Từ giờ bạn sẽ nhận thông báo công việc qua kênh này.',
        );
    }

    /**
     * Đăng ký webhook với Telegram.
     *
     * Nhập domain vào cấu hình thôi thì Telegram không biết gì — nó nằm ở phía bên kia và
     * không đọc được cơ sở dữ liệu của mình. Phải có đúng một cú gọi sang Telegram nói
     * "webhook của bot này là địa chỉ X"; hàm này là cú gọi đó, dùng chung cho nút trên màn
     * Cài đặt và lệnh telegram:set-webhook.
     *
     * @param  string|null  $url  Domain HTTPS ghi đè; bỏ trống thì lấy tg_webhook_url rồi tới APP_URL.
     * @return array{url: string, bot_username: string|null, secret_generated: bool}
     *
     * @throws RuntimeException khi thiếu bot token, domain không hợp lệ, hoặc Telegram từ chối.
     */
    public function registerWebhook(?string $url = null): array
    {
        if (! ($this->settings->getByKey('tg_bot_token')['value'] ?? null)) {
            throw new RuntimeException('Chưa cấu hình Bot Token. Nhập token rồi lưu trước khi đăng ký webhook.');
        }

        $base = rtrim((string) (
            $url
            ?: ($this->settings->getByKey('tg_webhook_url')['value'] ?? null)
            ?: config('app.url')
        ), '/');

        if (! str_starts_with($base, 'https://')) {
            throw new RuntimeException("Domain webhook phải là HTTPS, đang là: {$base}");
        }

        // Sinh sẵn khóa bí mật để không ai phải tự nghĩ chuỗi rồi copy nhầm giữa hai nơi —
        // webhook từ chối mọi request không kèm đúng chuỗi này.
        $secret = $this->settings->getByKey('tg_webhook_secret')['value'] ?? null;
        $secretGenerated = false;

        if (! $secret) {
            $secret = Str::random(48);
            $this->settings->update(['tg_webhook_secret' => $secret]);
            $secretGenerated = true;
        }

        $webhookUrl = "{$base}/api/telegram/webhook";

        $response = $this->callApi('setWebhook', [
            'url' => $webhookUrl,
            'secret_token' => $secret,
            // Chỉ nhận tin nhắn: bot gửi một chiều, các loại update khác chỉ tốn hàng đợi.
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ]);

        if (($response['ok'] ?? false) !== true) {
            throw new RuntimeException('Telegram từ chối đăng ký webhook: '.($response['description'] ?? 'không có phản hồi từ Telegram'));
        }

        return [
            'url' => $webhookUrl,
            'bot_username' => $this->botUsername(),
            'secret_generated' => $secretGenerated,
        ];
    }

    /**
     * Username của bot — cần cho deep link, không dựng được từ bot token.
     *
     * Lấy từ cấu hình nếu admin đã nhập; chưa có thì hỏi thẳng Telegram qua getMe rồi
     * lưu lại, để không phải bắt admin đi tra thủ công.
     */
    public function botUsername(): ?string
    {
        $configured = $this->settings->getByKey('tg_bot_username')['value'] ?? null;

        if ($configured) {
            return ltrim((string) $configured, '@');
        }

        $response = $this->callApi('getMe');
        $username = $response['result']['username'] ?? null;

        if ($username) {
            $this->settings->update(['tg_bot_username' => $username]);
        }

        return $username;
    }

    /**
     * Gọi Bot API. Server VN bị chặn HTTP/2 tới Telegram → ép HTTP/1.1 + IPv4 như TelegramChannel.
     */
    public function callApi(string $method, array $payload = []): array
    {
        $token = $this->settings->getByKey('tg_bot_token')['value'] ?? null;

        if (! $token) {
            return [];
        }

        try {
            return Http::timeout(30)
                ->withOptions(['curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                ]])
                ->post("https://api.telegram.org/bot{$token}/{$method}", $payload)
                ->json() ?? [];
        } catch (Throwable $e) {
            Log::warning("Telegram: gọi {$method} thất bại.", ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Gửi qua TelegramChannel để dùng chung phần cấu hình, phân loại lỗi và ghi log.
     */
    private function sendMessage(string $chatId, string $text): SendResult
    {
        return $this->notifier->send(new NotificationPayload(
            channels: ['telegram'],
            recipient: new Recipient(telegramChatId: $chatId),
            content: $text,
        ))[0];
    }

    private function profileOf(User $user): ?UserProfile
    {
        return UserProfile::where('user_id', $user->id)->first();
    }
}
