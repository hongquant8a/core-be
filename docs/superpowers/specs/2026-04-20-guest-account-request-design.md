# Guest "Request Account Opening" API — Design

**Date:** 2026-04-20
**Module:** Auth
**Status:** Approved

## 1. Mục đích

Cho phép khách (guest, chưa có tài khoản) gửi yêu cầu mở tài khoản qua 1 API public. Hệ thống forward yêu cầu này dưới dạng email tới `contact_email` cấu hình trong settings. Không lưu DB.

## 2. Endpoint

`POST /api/auth/request-account` — không cần xác thực.

**Middleware:** `throttle:5,10` (5 request / IP / 10 phút) + `log.activity` (kế thừa từ group cha).

**Mount tại:** [app/Modules/Auth/Routes/auth.php](../../../app/Modules/Auth/Routes/auth.php).

### Request body (JSON)

```json
{
  "full_name": "Nguyễn Văn A",
  "phone": "0901234567",
  "email": "a@example.com",
  "content": "Xin mở tài khoản phòng X..."
}
```

### Response

| Status | Khi nào | Body |
|---|---|---|
| 200 | Đã nhận và đã dispatch job gửi mail | `{ "success": true, "message": "Yêu cầu đã được gửi." }` |
| 422 | Validation fail | Standard Laravel validation errors |
| 429 | Throttle | Standard Laravel throttle response |
| 503 | `contact_email` chưa cấu hình hoặc `email_enabled = false` | `{ "success": false, "message": "Hệ thống chưa cấu hình email tiếp nhận. Vui lòng liên hệ quản trị." }` |

## 3. Setting mới

Thêm 1 dòng vào [database/seeders/SettingSeeder.php](../../../database/seeders/SettingSeeder.php), block `// General`:

```php
['key' => 'contact_email', 'value' => null, 'group' => 'general',
 'is_public' => false, 'type' => 'string',
 'label' => 'Email tiếp nhận yêu cầu liên hệ', 'sort_order' => 7],
```

Không cần migration mới — `SettingSeeder` dùng `updateOrCreate`. Triển khai chạy `php artisan db:seed --force`.

## 4. Components

### File mới

| File | Vai trò |
|---|---|
| [app/Modules/Auth/Requests/RequestAccountRequest.php](../../../app/Modules/Auth/Requests/RequestAccountRequest.php) | FormRequest validate 4 field, message tiếng Việt theo pattern `ForgotPasswordRequest`. |
| [app/Modules/Auth/Services/GuestAccountRequestService.php](../../../app/Modules/Auth/Services/GuestAccountRequestService.php) | Đọc `contact_email` từ `SettingService`. Nếu thiếu → trả status `not_configured`. Nếu đủ → dispatch Job. |
| [app/Modules/Auth/Jobs/SendGuestAccountRequestEmail.php](../../../app/Modules/Auth/Jobs/SendGuestAccountRequestEmail.php) | `implements ShouldQueue`, `public string $queue = 'notifications';`. Inject `MailChannel`. Render blade → build `Recipient` + `NotificationPayload(channels=['mail'])` → `MailChannel::send()`. |
| [resources/views/emails/guest_account_request.blade.php](../../../resources/views/emails/guest_account_request.blade.php) | Extends `emails.notification-layout`. Hiển thị 4 field trong info-table (Họ tên / SĐT / Email / Nội dung yêu cầu). Subject: `Yêu cầu mở tài khoản từ {full_name}`. |

### File sửa

| File | Sửa |
|---|---|
| [app/Modules/Auth/AuthController.php](../../../app/Modules/Auth/AuthController.php) | Thêm action `requestAccount(RequestAccountRequest $req)` gọi service, trả 200/503. Inject `GuestAccountRequestService` qua constructor. |
| [app/Modules/Auth/Routes/auth.php](../../../app/Modules/Auth/Routes/auth.php) | Thêm `Route::post('/request-account', [AuthController::class, 'requestAccount'])->middleware('throttle:5,10');`. |
| [database/seeders/SettingSeeder.php](../../../database/seeders/SettingSeeder.php) | Thêm 1 dòng `contact_email` (xem section 3). |

## 5. Data flow

```
Client POST /api/auth/request-account
   │
   ▼
[throttle:5,10] → [log.activity]
   │
   ▼
AuthController::requestAccount(RequestAccountRequest)
   │
   ▼
GuestAccountRequestService::handle($validated)
   ├─ contactEmail = SettingService->getByKey('contact_email')['value']
   ├─ emailEnabled = (bool) SettingService->getByKey('email_enabled')['value']
   ├─ if !contactEmail || !emailEnabled → return ['ok' => false, 'reason' => 'not_configured']
   └─ dispatch(SendGuestAccountRequestEmail::class, [contactEmail, $validated])
        │
        ▼ (queue=notifications, async)
   SendGuestAccountRequestEmail::handle(MailChannel $mail)
        ├─ html = view('emails.guest_account_request', $validated + layout vars)->render()
        ├─ recipient = new Recipient(email: $contactEmail, name: 'Quản trị')
        ├─ payload = new NotificationPayload(
        │     channels: ['mail'],
        │     recipient: $recipient,
        │     content: $html,
        │     subject: 'Yêu cầu mở tài khoản từ '.$fullName,
        │   )
        └─ $mail->send($recipient, $payload)
              └─ MailChannel handles SMTP runtime config + email_enabled re-check + sender info
```

## 6. Validation rules

```php
// RequestAccountRequest::rules()
return [
    'full_name' => 'required|string|max:150',
    'phone'     => 'required|string|max:20',
    'email'     => 'required|email|max:150',
    'content'   => 'required|string|max:2000',
];
```

Messages tiếng Việt cho từng rule (pattern y `ForgotPasswordRequest`).

## 7. Error handling

- **`contact_email` rỗng hoặc `email_enabled = false`:** Service không dispatch job → controller trả 503. Lý do: dispatch một job chắc chắn fail (MailChannel sẽ trả `fail('Mail is disabled')`) là vô ích, tốn queue + log.
- **Job fail (SMTP error, connection timeout, ...):** Queue worker đã chạy với `--tries=3` ([docs/deploy.txt:27](../../../docs/deploy.txt#L27)). Sau 3 lần fail vào `failed_jobs`. Client đã nhận 200 từ trước — chấp nhận eventual consistency, đây là luồng guest, không có user session để báo lỗi về.
- **Throttle (429):** Laravel default response, không custom.

## 8. Verification (manual)

1. **Setting chưa có:** không seed `contact_email` → POST trả 503.
2. **Setting có, `email_enabled=false`:** POST trả 503.
3. **Đủ điều kiện:** seed `contact_email='admin@test.com'`, set `email_enabled=true` + SMTP config hợp lệ → POST trả 200, queue worker pick job, mail tới `admin@test.com` với:
   - Subject: `Yêu cầu mở tài khoản từ Nguyễn Văn A`
   - Body: 4 field hiển thị trong bảng có style theo notification-layout.
4. **Throttle:** 6 POST liên tiếp từ cùng IP trong 10 phút → request thứ 6 trả 429.
5. **Validation:** thiếu `full_name` → 422 với message tiếng Việt.

## 9. YAGNI / Out of scope

- Không lưu DB (`account_open_requests` table) — đã quyết.
- Không có trang admin xem danh sách yêu cầu — log.activity đã ghi lại request, đủ.
- Không có honeypot field — throttle đã đủ chắn spam phổ thông.
- Không gửi mail xác nhận lại cho guest — chỉ forward 1 chiều tới admin.
- Không có endpoint duyệt/từ chối yêu cầu — admin xử lý ngoài hệ thống (nhận mail rồi tạo user qua trang admin).
