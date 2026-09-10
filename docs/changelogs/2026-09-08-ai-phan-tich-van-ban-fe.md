# Phân tích văn bản bằng AI chuyển từ FE sang BE

> Ngày tạo: 16:03:09 08/09/2026  
> Cập nhật lần cuối: 16:03:09 08/09/2026

Trước đây nút "Phân Tích AI" ở màn Văn bản giao việc gọi thẳng `api.deepseek.com` qua proxy
`/ai` khai trong `vite.config.js`. Proxy đó gắn sẵn `Authorization` từ `DEEPSEEK_API_KEY`
nhưng **không kiểm tra đăng nhập** — ai chạm được cổng dev server là dùng được API key. Bản
build production lại không có proxy nên tính năng chết. Nay luồng đi qua BE.

---

## 1. Endpoint mới

| Method | Path | Quyền | Giới hạn |
|---|---|---|---|
| `POST` | `/api/task-assignment-documents/analyze` | `task-assignment-documents.analyze` | `throttle:10,1` |

**Request:** `{ "content": "<văn bản thô>" }` — bắt buộc, 20–50.000 ký tự.

**Response 200:**

```json
{
  "success": true,
  "message": "Phân tích văn bản thành công!",
  "data": {
    "title": "THÔNG BÁO Kết luận cuộc họp giao ban tháng 9",
    "date": "05/09/2026",
    "document_type": "THÔNG BÁO",
    "summary": "...",
    "tasks": [
      {
        "assignee": "Văn phòng Đảng ủy",
        "coordinators": ["Ban Xây dựng Đảng"],
        "content": "Tổng hợp báo cáo kết quả công tác quý III",
        "priority": "high",
        "deadline": "20/09/2026",
        "has_deadline": true
      }
    ]
  }
}
```

`date`, `document_type`, `deadline` trả `null` khi văn bản không ghi — không suy đoán.

**Mã lỗi:**

| Mã | Khi nào | message |
|---|---|---|
| 401 | Chưa đăng nhập | `Unauthenticated.` |
| 403 | Thiếu permission | `Bạn không có quyền thực hiện thao tác này.` |
| 422 | `content` sai rule, hoặc văn bản dài tới mức kết quả bị cắt | thông báo tiếng Việt |
| 429 | Quá 10 lần/phút | throttle mặc định |
| 502 | DeepSeek lỗi / không kết nối được / trả về không phải JSON | thông báo tiếng Việt |
| 503 | Chưa cấu hình URL hoặc token DeepSeek | `Chưa cấu hình DeepSeek (URL/Token) trong Cài đặt chung.` |

Nguyên nhân chi tiết (body lỗi của DeepSeek) ghi vào log, không trả ra client.

## 2. FE phải đổi gì

1. `AiAnalysisService.js` gọi `$api('${API_BASE}/analyze', { method: 'POST', body: { content } })`
   thay cho `fetch('/ai/chat/completions')`. Prompt, model, temperature, max_tokens, retry đã
   chuyển hết sang BE — FE không giữ bản sao nào.
2. BE trả `document_type` (snake_case); service map sang `documentType` cho view, các field
   còn lại giữ nguyên tên. **Shape mà view nhận không đổi.**
3. Bỏ khối proxy `/ai` trong `vite.config.js` và biến `DEEPSEEK_API_KEY` trong `core-fe/.env`
   + `.env.example`. Nginx production **không cần** block `/ai` nữa.
4. Toast lỗi ưu tiên `message` từ BE (`e.userMessage`) rồi mới rơi về chuỗi i18n chung.

## 3. Cấu hình

Key đặt ở **BE**, theo thứ tự ưu tiên:

1. Bảng `settings`: `api_deepseek_url`, `api_deepseek_token` (màn Cài đặt chung → DeepSeek).
   Hai ô này có sẵn trên UI từ lâu nhưng trước đây không code nào đọc — nay đã có tác dụng.
2. `config/services.php` → `.env`: `DEEPSEEK_BASE_URL`, `DEEPSEEK_API_KEY`, `DEEPSEEK_MODEL`.

## 4. Sau khi deploy

```bash
sail artisan db:seed --class=PermissionSeeder   # tạo permission task-assignment-documents.analyze
sail artisan config:clear
```

Vai trò **Quản lý công việc** và **Super Admin** tự có quyền này. Vai trò khác muốn dùng thì
quản trị cấp thêm ở màn Vai trò.
