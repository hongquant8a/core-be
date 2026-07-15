# API Thông báo kiểm thử (Notification Test) – Core

> Cập nhật lần cuối: 15:10:25 15/07/2026 — bổ sung kênh Telegram, tách rõ Zalo OA vs Zalo ZNS (2 channel key khác nhau, trước đây tài liệu gộp chung nhầm).

Gửi thông báo kiểm thử qua các kênh SMS / Mail / Zalo OA / Zalo ZNS / FCM / Telegram để xác minh cấu hình hệ thống.

**Base path:** `/api/notifications`

---

## Gửi thông báo kiểm thử

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/notifications/test` |
| **Auth** | Bearer token (Sanctum) |
| **Permission** | `notifications.test` (Super Admin, Admin) |

**Body (JSON):**

| Field | Type | Required | Mô tả |
|-------|------|----------|-------|
| `channels` | `string[]` | ✅ | Danh sách kênh gửi: `sms`, `mail`, `zalo`, `zalo_zns`, `fcm`, `telegram` |
| `content` | `string` | ✅ | Nội dung thông báo (tối đa 500 ký tự) |
| `phone` | `string` | | Số điện thoại (kênh `sms`, `zalo_zns`). Định dạng `0xxx` hoặc `84xxx` |
| `email` | `string` | | Email người nhận (kênh `mail`) |
| `zalo_id` | `string` | | Zalo user_id người nhận (kênh `zalo` — Zalo OA, KHÔNG phải kênh ZNS) |
| `fcm_token` | `string` | | FCM device registration token (kênh `fcm`) |
| `telegram_chat_id` | `string` | | Telegram chat ID người nhận (kênh `telegram`, lấy được sau khi user start bot) |
| `name` | `string` | | Tên người nhận |
| `subject` | `string` | | Tiêu đề (kênh `mail`, `fcm`) |
| `context` | `object` | | Dữ liệu bổ sung — mỗi kênh dùng khác nhau (xem chi tiết bên dưới) |

**Response:** mảng `SendResult[]`, mỗi kênh 1 phần tử theo thứ tự `channels`:

```json
{
  "success": true,
  "data": [
    { "channel": "sms",      "success": true,  "message_id": "1",       "error": null },
    { "channel": "mail",     "success": true,  "message_id": null,      "error": null },
    { "channel": "zalo",     "success": false, "message_id": null,      "error": "[-124] access_token hết hạn..." },
    { "channel": "zalo_zns", "success": false, "message_id": null,      "error": "[541] Invalid Zalo Sender" },
    { "channel": "fcm",      "success": true,  "message_id": "msg-abc", "error": null },
    { "channel": "telegram", "success": true,  "message_id": "123456",  "error": null }
  ],
  "message": "Gửi thông báo kiểm thử hoàn tất"
}
```

---

## Chi tiết từng kênh

### 1. SMS (PSC SOAP)

Gửi tin nhắn SMS không dấu qua webservice SOAP của PSC (DVC-Danang).

**Payload cần thiết:**

| Field | Vai trò |
|-------|---------|
| `phone` | ✅ Số điện thoại nhận. `0xxx` tự động chuyển thành `84xxx` |
| `content` | ✅ Nội dung tin nhắn. Tự động bỏ dấu + thêm prefix `Thong bao: ` nếu chưa có |
| `subject` | Không dùng |
| `context` | Không dùng (chỉ ghi log) |

**Ví dụ:**
```json
{
  "channels": ["sms"],
  "phone": "0905112233",
  "content": "Công việc mới được giao cho bạn"
}
```

**Response thành công:** `message_id` = mã trả về từ PSC (số nguyên >= 0)
**Response thất bại:** `"SMS not configured"`, `"Missing phone"`, `"Invalid phone format"`, hoặc lỗi SOAP

---

### 2. Mail (SMTP + Blade template)

Gửi email HTML sử dụng blade template `emails.notification` qua SMTP với config từ DB settings.

**Payload cần thiết:**

| Field | Vai trò |
|-------|---------|
| `email` | ✅ Email người nhận |
| `content` | ✅ Nội dung chính (hiển thị trong content block của template) |
| `name` | Tên người nhận (hiển thị greeting `Xin chào {name}`) |
| `subject` | Tiêu đề email (mặc định: `"Thông báo"`) |
| `context` | Hiển thị dạng bảng key-value trong email (optional) |
| `phone` | Không dùng |

**Ví dụ:**
```json
{
  "channels": ["mail"],
  "email": "admin@example.com",
  "name": "Anh Thái",
  "content": "Bạn đã được giao công việc mới. Vui lòng kiểm tra hệ thống.",
  "subject": "Công việc mới",
  "context": {
    "Mã công việc": "CV-001",
    "Hạn hoàn thành": "20/04/2026"
  }
}
```

**Template email:** `resources/views/emails/notification.blade.php`
- Header xanh với tiêu đề
- Greeting `Xin chào {name}` (nếu có)
- Content block highlight
- Bảng context key-value (nếu có)
- Footer sender name

**Response thành công:** `message_id` = `null` (SMTP không trả ID)
**Response thất bại:** `"Mail not configured"`, `"Missing sender email address"`, `"Missing recipient email"`, hoặc lỗi SMTP

---

### 3. Zalo — 2 kênh riêng biệt: `zalo` (OA tự do) và `zalo_zns` (template)

⚠️ Đây là 2 channel key khác nhau hoàn toàn, mỗi kênh có config/settings riêng — không dùng chung.

#### 3a. `zalo` — Zalo OA (nhắn text tự do)

Gửi tin nhắn text tự do qua Official Account API (`openapi.zalo.me`), không cần template.

**Payload cần thiết:**

| Field | Vai trò |
|-------|---------|
| `zalo_id` | ✅ Zalo user_id người nhận (lấy từ webhook follow / OAuth, KHÔNG phải số điện thoại) |
| `content` | ✅ Nội dung tin nhắn text tự do |
| `phone`, `context`, `subject`, `email` | Không dùng |

**Logic:** dùng `access_token` từ settings (`zalo_access_token`); nếu token hết hạn và có đủ `zalo_app_id`/`zalo_app_secret`/`zalo_refresh_token` thì tự refresh 1 lần rồi gửi lại.

**Ví dụ:**
```json
{
  "channels": ["zalo"],
  "zalo_id": "1234567890123456789",
  "content": "Bạn có công việc mới cần xử lý"
}
```

**Response thành công:** `message_id` từ Zalo OA
**Response thất bại:** `"Kênh Zalo đang bị tắt."`, `"Zalo not configured (missing access_token)"`, `"Missing Zalo user_id"`, hoặc `"[errorcode] message"` (vd `-124`/`-125`/`-216`/`1006` = access token hết hạn)

#### 3b. `zalo_zns` — Zalo ZNS (South Telecom / WorldSMS relay)

Gửi Zalo Notification Service (ZNS) theo **template** — không gửi text tự do. Nội dung tin phụ thuộc vào template đã đăng ký với Zalo OA, gửi qua relay South Telecom.

**Payload cần thiết:**

| Field | Vai trò |
|-------|---------|
| `phone` | ✅ Số điện thoại nhận. `0xxx` tự động chuyển thành `84xxx` |
| `content` | Không dùng cho ZNS (vẫn bắt buộc trong request vì shared validation) |
| `context` | ✅ Chính là `template_data` — key phải khớp placeholder trong template ZNS |
| `subject` | Không dùng |
| `email` | Không dùng |

**Logic:**
- `template_id` lấy từ settings (`zns_template_id`), admin cấu hình sẵn (context có thể override qua key `_template_id`)
- `template_data` = merge `zns_extra_params` (default từ settings) với `context` (caller). Context override nếu trùng key
- `client_req_id` auto UUID
- `from` = `zns_sender` (OA ID từ settings)
- Nếu cấu hình `zns_sms_failover_sender` → gắn kèm SMS failover trong cùng request

**Ví dụ:**
```json
{
  "channels": ["zalo_zns"],
  "phone": "0905112233",
  "content": "placeholder",
  "context": {
    "customer_name": "Anh Thái",
    "company": "Danatec",
    "billcode": "HD712166",
    "phone": "84905112233"
  }
}
```

**Response thành công:** `message_id` = `tracking_id` từ South Telecom
**Response thất bại:** `"Kênh Zalo ZNS đang bị tắt."`, `"Zalo ZNS chưa cấu hình (thiếu server/username/password)."`, `"Thiếu Zalo OA Sender ID (zns_sender)."`, `"Thiếu template_id..."`, `"Số điện thoại không hợp lệ..."`, hoặc `"[errorcode] description"`

> **Lưu ý:** `status: 1` chỉ là South Telecom đã nhận request, không phải tin đã gửi thành công đến người nhận. Cần DLR để xác nhận.

---

### 4. FCM (Firebase Cloud Messaging)

Gửi push notification đến thiết bị di động qua Firebase legacy HTTP API.

**Payload cần thiết:**

| Field | Vai trò |
|-------|---------|
| `fcm_token` | ✅ FCM device registration token (lấy từ mobile app) |
| `content` | ✅ Nội dung notification (`body`) |
| `subject` | Tiêu đề notification (`title`, mặc định: `"Thông báo"`) |
| `context` | Gửi kèm dưới dạng `data` payload (app xử lý, optional) |
| `phone` | Không dùng |
| `email` | Không dùng |

**Ví dụ:**
```json
{
  "channels": ["fcm"],
  "fcm_token": "dK8x...device-registration-token...mN2",
  "content": "Bạn có công việc mới cần xử lý",
  "subject": "Công việc mới",
  "context": {
    "task_id": 42,
    "action": "open_task"
  }
}
```

**FCM payload gửi đến Firebase:**
```json
{
  "to": "dK8x...device-token...mN2",
  "notification": {
    "title": "Công việc mới",
    "body": "Bạn có công việc mới cần xử lý"
  },
  "data": {
    "task_id": 42,
    "action": "open_task"
  }
}
```

**Response thành công:** `message_id` = message ID từ Firebase
**Response thất bại:** `"FCM is disabled"`, `"FCM not configured"`, `"Missing FCM device token"`, hoặc lỗi Firebase (`InvalidRegistration`, `NotRegistered`, ...)

---

### 5. Telegram

Gửi tin nhắn đến người dùng qua Telegram Bot API. Server VN bị chặn HTTP/2 tới Telegram nên client backend force HTTP/1.1 + IPv4.

**Payload cần thiết:**

| Field | Vai trò |
|-------|---------|
| `telegram_chat_id` | ✅ Chat ID người nhận (lấy được sau khi user start bot) |
| `content` | ✅ Nội dung tin nhắn — hỗ trợ `parse_mode=HTML` |
| `phone`, `email`, `fcm_token`, `context` | Không dùng |

**Ví dụ:**
```json
{
  "channels": ["telegram"],
  "telegram_chat_id": "123456789",
  "content": "Bạn có công việc mới cần xử lý"
}
```

**Response thành công:** `message_id` = message ID từ Telegram
**Response thất bại:** `"Kênh Telegram đang bị tắt."`, hoặc lỗi Telegram Bot API (chat not found, bot token sai...)

---

### 6. Gửi nhiều kênh cùng lúc

```json
{
  "channels": ["sms", "mail", "zalo", "zalo_zns", "fcm", "telegram"],
  "phone": "0905112233",
  "email": "admin@example.com",
  "zalo_id": "1234567890123456789",
  "fcm_token": "dK8x...token...mN2",
  "telegram_chat_id": "123456789",
  "name": "Anh Thái",
  "content": "Công việc mới được giao cho bạn",
  "subject": "Công việc mới",
  "context": {
    "customer_name": "Anh Thái",
    "company": "Danatec",
    "task_id": 42
  }
}
```

Mỗi kênh tự lấy field cần thiết, bỏ qua field không liên quan. Response trả mảng kết quả theo đúng thứ tự + số lượng `channels` truyền vào. Nếu 1 kênh fail, các kênh khác vẫn gửi bình thường.

---

## Cấu hình cần thiết

Cấu hình trong trang **Cấu hình hệ thống** (`/api/settings`):

### SMS (PSC SOAP)

| Setting key | Mô tả |
|-------------|-------|
| `sms_enabled` | Bật/tắt kênh SMS |
| `sms_server` | URL webservice (`http://49.156.52.24:5993/SmsService.asmx`) |
| `sms_username` | Tài khoản PSC |
| `sms_password` | Mật khẩu PSC |

### Mail (SMTP)

| Setting key | Mô tả |
|-------------|-------|
| `email_enabled` | Bật/tắt kênh Email |
| `email_smtp_host` | Máy chủ SMTP (vd `smtp.gmail.com`) |
| `email_smtp_port` | Cổng SMTP (mặc định `587`) |
| `email_smtp_username` | Tài khoản SMTP |
| `email_smtp_password` | Mật khẩu SMTP |
| `email_smtp_encryption` | Loại bảo mật (`tls` hoặc `ssl`, mặc định `tls`) |
| `email_sender_address` | Địa chỉ email gửi (vd `noreply@danatec.vn`) |
| `email_sender_name` | Tên người gửi (vd `Hệ thống Danatec`) |

### Zalo OA (kênh `zalo` — nhắn text tự do)

| Setting key | Mô tả |
|-------------|-------|
| `zalo_enabled` | Bật/tắt kênh Zalo OA |
| `zalo_app_id` / `zalo_app_secret` | App credentials của Official Account |
| `zalo_access_token` | Access token hiện tại (auto refresh, TTL 1h) |
| `zalo_refresh_token` | Refresh token (TTL 3 tháng, single-use) |

### Zalo ZNS (kênh `zalo_zns` — nhắn theo template, qua South Telecom)

| Setting key | Mô tả |
|-------------|-------|
| `zns_enabled` | Bật/tắt kênh Zalo ZNS |
| `zns_server` | URL API (`https://api-04.worldsms.vn/apidebit/sendZNS`) |
| `zns_username` | Tài khoản South Telecom |
| `zns_password` | Mật khẩu South Telecom |
| `zns_sender` | Zalo OA ID (`from`) |
| `zns_template_id` | Template ID ZNS |
| `zns_extra_params` | JSON default template data (optional, vd `{"company":"Danatec"}`) |
| `zns_sms_failover_sender` / `zns_sms_failover_unicode` | Cấu hình SMS failover đi kèm ZNS (optional) |

### FCM (Firebase)

| Setting key | Mô tả |
|-------------|-------|
| `firebase_service_account` | Firebase service account JSON object |
| `fcm_enabled` | Bật/tắt FCM (`1` = bật, `0` = tắt) |

### Telegram (kênh `telegram`)

| Setting key | Mô tả |
|-------------|-------|
| `tg_enabled` | Bật/tắt kênh Telegram |
| `tg_bot_token` | Bot token từ BotFather |

---

## Lưu ý chung

- Số điện thoại `0xxx` tự động chuyển thành `84xxx` (SMS và Zalo ZNS).
- Nội dung SMS tự động bỏ dấu và thêm prefix `Thong bao: ` nếu chưa có.
- Log gửi thông báo lưu trong bảng `notification_deliveries` (xem API theo module: `/api/task-assignment/notification-config/logs`, `/api/meeting/notification-config/logs`, `/api/schedules/notification-config/logs` — không phải 1 endpoint `/api/notifications/logs` chung).
- Mỗi kênh độc lập — 1 kênh fail không ảnh hưởng kênh khác.
- Tất cả lỗi được bắt và trả về `SendResult` (không throw exception ra caller).

---

## Mã lỗi Zalo ZNS

| errorcode | Mô tả |
|-----------|-------|
| 40 | Unauthorized |
| 41 | Sai password |
| 42 | Sai user |
| 51 | IP chưa khai báo |
| 52 | Tham số không hợp lệ |
| 53 | Số điện thoại không hợp lệ |
| 54 | Sender không hợp lệ |
| 541 | Zalo Sender không hợp lệ |
| 55 | Nội dung không hợp lệ |
| 557 | Sai format template |
| 811 | Tài khoản không được phép gửi ZNS |
| 82 | Hết quota |

## Lỗi FCM phổ biến

| Error | Mô tả |
|-------|-------|
| `InvalidRegistration` | Device token không hợp lệ |
| `NotRegistered` | App đã uninstall hoặc token hết hạn |
| `MessageTooBig` | Payload vượt 4KB |
| `MismatchSenderId` | Server key không khớp project |
| `Unavailable` | FCM server tạm thời không khả dụng |
