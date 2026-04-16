# API Thông báo kiểm thử (Notification Test) – Core

Gửi thông báo kiểm thử qua các kênh SMS / Mail / Zalo / FCM để xác minh cấu hình hệ thống.

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
| `channels` | `string[]` | ✅ | Danh sách kênh gửi: `sms`, `mail`, `zalo`, `fcm` |
| `content` | `string` | ✅ | Nội dung thông báo (tối đa 500 ký tự) |
| `phone` | `string` | | Số điện thoại (kênh `sms`, `zalo`). Định dạng `0xxx` hoặc `84xxx` |
| `email` | `string` | | Email người nhận (kênh `mail`) |
| `zalo_id` | `string` | | Zalo ID người nhận |
| `fcm_token` | `string` | | FCM device registration token (kênh `fcm`) |
| `name` | `string` | | Tên người nhận |
| `subject` | `string` | | Tiêu đề (kênh `mail`, `fcm`) |
| `context` | `object` | | Dữ liệu bổ sung — mỗi kênh dùng khác nhau (xem chi tiết bên dưới) |

**Response:** mảng `SendResult[]`, mỗi kênh 1 phần tử theo thứ tự `channels`:

```json
{
  "success": true,
  "data": [
    { "channel": "sms",  "success": true,  "message_id": "1",    "error": null },
    { "channel": "mail", "success": true,  "message_id": null,   "error": null },
    { "channel": "zalo", "success": false, "message_id": null,   "error": "[541] Invalid Zalo Sender" },
    { "channel": "fcm",  "success": true,  "message_id": "msg-abc", "error": null }
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

### 3. Zalo ZNS (South Telecom)

Gửi Zalo Notification Service (ZNS) theo **template** — không gửi text tự do. Nội dung tin phụ thuộc vào template đã đăng ký với Zalo OA.

**Payload cần thiết:**

| Field | Vai trò |
|-------|---------|
| `phone` | ✅ Số điện thoại nhận. `0xxx` tự động chuyển thành `84xxx` |
| `content` | Không dùng cho Zalo (vẫn bắt buộc trong request vì shared validation) |
| `context` | ✅ Chính là `template_data` — key phải khớp placeholder trong template ZNS |
| `subject` | Không dùng |
| `email` | Không dùng |

**Logic:**
- `template_id` lấy từ settings (`zalo_template_id`), admin cấu hình sẵn
- `template_data` = merge `zalo_extra_params` (default từ settings) với `context` (caller). Context override nếu trùng key
- `client_req_id` auto UUID
- `from` = `zalo_sender` (OA ID từ settings)

**Ví dụ:**
```json
{
  "channels": ["zalo"],
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
**Response thất bại:** `"Zalo not configured"`, `"Missing Zalo OA sender ID"`, `"Missing Zalo template ID"`, hoặc `"[errorcode] description"`

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

### 5. Gửi nhiều kênh cùng lúc

```json
{
  "channels": ["sms", "mail", "zalo", "fcm"],
  "phone": "0905112233",
  "email": "admin@example.com",
  "fcm_token": "dK8x...token...mN2",
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

Mỗi kênh tự lấy field cần thiết, bỏ qua field không liên quan. Response trả mảng 4 kết quả theo thứ tự `channels`. Nếu 1 kênh fail, các kênh khác vẫn gửi bình thường.

---

## Cấu hình cần thiết

Cấu hình trong trang **Cấu hình hệ thống** (`/api/settings`):

### SMS (PSC SOAP)

| Setting key | Mô tả |
|-------------|-------|
| `sms_server` | URL webservice (`http://49.156.52.24:5993/SmsService.asmx`) |
| `sms_username` | Tài khoản PSC |
| `sms_password` | Mật khẩu PSC |

### Mail (SMTP)

| Setting key | Mô tả |
|-------------|-------|
| `email_smtp_host` | Máy chủ SMTP (vd `smtp.gmail.com`) |
| `email_smtp_port` | Cổng SMTP (mặc định `587`) |
| `email_smtp_username` | Tài khoản SMTP |
| `email_smtp_password` | Mật khẩu SMTP |
| `email_smtp_encryption` | Loại bảo mật (`tls` hoặc `ssl`, mặc định `tls`) |
| `email_sender_address` | Địa chỉ email gửi (vd `noreply@danatec.vn`) |
| `email_sender_name` | Tên người gửi (vd `Hệ thống Danatec`) |

### Zalo ZNS (South Telecom)

| Setting key | Mô tả |
|-------------|-------|
| `zalo_server` | URL API (`https://api-04.worldsms.vn/apidebit/sendZNS`) |
| `zalo_username` | Tài khoản South Telecom |
| `zalo_password` | Mật khẩu South Telecom |
| `zalo_sender` | Zalo OA ID (`from`) |
| `zalo_template_id` | Template ID ZNS |
| `zalo_extra_params` | JSON default template data (optional, vd `{"company":"Danatec"}`) |

### FCM (Firebase)

| Setting key | Mô tả |
|-------------|-------|
| `api_firebase_url` | URL Firebase (`https://fcm.googleapis.com/fcm/send`) |
| `api_firebase_token` | Server key (từ Firebase Console → Project Settings → Cloud Messaging) |
| `api_firebase_enabled` | Bật/tắt FCM (`1` = bật, `0` = tắt) |

---

## Lưu ý chung

- Số điện thoại `0xxx` tự động chuyển thành `84xxx` (SMS và Zalo).
- Nội dung SMS tự động bỏ dấu và thêm prefix `Thong bao: ` nếu chưa có.
- Log gửi thông báo: `storage/logs/notification-YYYY-MM-DD.log`.
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
