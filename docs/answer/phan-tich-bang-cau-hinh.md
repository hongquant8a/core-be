# Phân tích thiết kế bảng cấu hình hệ thống

**Ngày tạo:** 2026-02-23  
**Mục đích:** Thiết kế bảng cấu hình (settings) để lưu trữ thông tin cấu hình động của ứng dụng, hỗ trợ cập nhật qua API, phân loại công khai/bảo mật.

---

## 1. Tổng quan yêu cầu

### 1.1 Các nhóm cấu hình

| Nhóm | Key | Mô tả | Kiểu dữ liệu | Công khai |
|------|-----|-------|--------------|-----------|
| **Cấu hình thông tin chung** | | | | |
| | copyright | Thông tin bản quyền | string | ✓ |
| | designed_by | Thiết kế bởi | string | ✓ |
| | language | Ngôn ngữ mặc định | string | ✓ |
| | time_format | Định dạng thời gian | string | ✓ |
| | icon | Biểu tượng favicon | string (URL/media_id) | ✓ |
| | logo | Logo trang | string (URL/media_id) | ✓ |
| **Cấu hình trang quản trị** | | | | |
| | admin_app_name | Tên ứng dụng | string | ✓ |
| | admin_logo_title | Tiêu đề logo | string | ✓ |
| | admin_welcome_title | Tiêu đề chào mừng | string | ✓ |
| | admin_app_description | Mô tả ứng dụng | text | ✓ |
| | admin_background_image | Ảnh nền | string (URL/media_id) | ✓ |
| **Cấu hình trang chọn tổ chức** | | | | |
| | org_select_title | Tiêu đề trang chọn tổ chức | string | ✓ |
| | org_select_description | Mô tả trang chọn tổ chức | text | ✓ |
| | org_select_background_image | Ảnh nền | string (URL/media_id) | ✓ |
| **Thông tin mạng xã hội** | | | | |
| | social_facebook | Facebook URL | string | ✓ |
| | social_twitter | Twitter URL | string | ✓ |
| | social_youtube | YouTube URL | string | ✓ |
| | social_tiktok | TikTok URL | string | ✓ |
| | social_gmail | Gmail | string | ✓ |
| | social_email | Email liên hệ | string | ✓ |
| **Kết nối API** | | | | |
| | api_gemini_url | Gemini API URL | string | ✗ |
| | api_gemini_token | Gemini Token | string | ✗ |
| | api_deepseek_url | DeepSeek API URL | string | ✗ |
| | api_deepseek_token | DeepSeek Token | string | ✗ |
| | api_chatgpt_url | ChatGPT API URL | string | ✗ |
| | api_chatgpt_token | ChatGPT Token | string | ✗ |
| | firebase_service_account | Firebase Service Account JSON | json | ✗ |
| | api_firebase_enabled | Bật/tắt Firebase | boolean | ✗ |
| | api_google_maps_url | Google Maps API URL | string | ✗ |
| | api_google_maps_token | Google Maps Token | string | ✗ |
| **Cấu hình Email** | | | | |
| | email_protocol | Giao thức (smtp, mail, sendmail) | string | ✗ |
| | email_sender_name | Tên người gửi | string | ✗ |
| | email_sender_address | Địa chỉ email gửi | string | ✗ |
| | email_smtp_host | Máy chủ SMTP | string | ✗ |
| | email_smtp_username | Tài khoản SMTP | string | ✗ |
| | email_smtp_password | Mật khẩu SMTP | string | ✗ |
| | email_smtp_port | Cổng SMTP | string | ✗ |
| | email_smtp_encryption | Loại bảo mật (tls, ssl, null) | string | ✗ |
| | email_test_address | Email kiểm thử | string | ✗ |
| **Cấu hình SMS** | | | | |
| | sms_server | Máy chủ SMS | string | ✗ |
| | sms_username | Tên đăng nhập | string | ✗ |
| | sms_password | Mật khẩu | string | ✗ |
| | sms_test_phone | Số điện thoại kiểm thử | string | ✗ |
| **Cấu hình Zalo** | | | | |
| | zalo_server | Máy chủ Zalo | string | ✗ |
| | zalo_username | Tên đăng nhập | string | ✗ |
| | zalo_password | Mật khẩu | string | ✗ |
| | zalo_sender | Người gửi | string | ✗ |
| | zalo_template_id | Mẫu tin nhắn ID | string | ✗ |
| | zalo_extra_params | Tham số bổ sung (JSON/array) | json | ✗ |
| **Cấu hình chat nội bộ** | | | | |
| | chat_server | Máy chủ Chat | string | ✗ |
| | chat_api_key | API Key | string | ✗ |
| | chat_sender | Người gửi | string | ✗ |
| | chat_receiver | Người nhận | string | ✗ |
| | chat_room | Phòng chat | string | ✗ |
| | chat_message | Tin nhắn | string | ✗ |
| | chat_department | Phòng ban | string | ✗ |
| | chat_email_title | Tiêu đề mail | string | ✗ |
| | chat_test_type | Loại kiểm tra | string | ✗ |
| **Cấu hình nhật ký** | | | | |
| | log_retention_days | Số ngày giữ nhật ký trước khi xóa định kỳ | int | ✗ |

### 1.2 Chức năng

1. **Cập nhật thông tin:** API PATCH/PUT cho phép admin cập nhật toàn bộ hoặc từng phần cấu hình (yêu cầu xác thực + quyền `settings.update`).
2. **Lấy thông tin công khai:** API GET public trả về chỉ các key có `is_public = true` (không cần xác thực).
3. **Lấy thông tin đầy đủ:** API GET authenticated trả về toàn bộ cấu hình (yêu cầu xác thực + quyền `settings.show` hoặc `settings.index`).

---

## 2. Thiết kế cơ sở dữ liệu

### 2.1 Phương án: Bảng key-value (settings)

Dùng bảng `settings` lưu cặp key-value, có nhóm và flag công khai.

**Ưu điểm:**
- Linh hoạt: thêm key mới qua seeder/migration mà không cần thay đổi schema.
- Dễ mở rộng nhóm mới.
- Truy vấn đơn giản, cache dễ dàng.

**Bảng `settings`:**

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| key | varchar(255) | No | — | UNIQUE |
| value | text | Yes | null | Giá trị cấu hình |
| group | varchar(100) | No | 'general' | general, admin_page, org_select_page, social, api, log, email, sms, zalo, chat |
| is_public | boolean | No | true | true = trả về khi gọi API công khai |
| type | varchar(50) | No | 'string' | string, text, integer, boolean, json |
| label | varchar(255) | Yes | null | Nhãn hiển thị tiếng Việt (cho form admin) |
| sort_order | int unsigned | No | 0 | Thứ tự hiển thị trong nhóm |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Index:** UNIQUE(key).

### 2.2 Các nhóm (group)

| group | Mô tả |
|-------|-------|
| general | Cấu hình thông tin chung |
| admin_page | Cấu hình trang quản trị |
| org_select_page | Cấu hình trang chọn tổ chức |
| social | Thông tin mạng xã hội |
| api | Kết nối API (bảo mật) |
| email | Cấu hình Email (bảo mật) |
| sms | Cấu hình SMS (bảo mật) |
| zalo | Cấu hình Zalo (bảo mật) |
| chat | Cấu hình chat nội bộ (bảo mật) |
| log | Cấu hình nhật ký (bảo mật) |

---

## 3. API thiết kế

### 3.1 Endpoints

| Method | Path | Mô tả | Auth | Permission |
|--------|------|-------|------|------------|
| GET | /api/settings/public | Lấy cấu hình công khai (is_public=true) | Không | — |
| GET | /api/settings | Lấy toàn bộ cấu hình (nhóm theo group) | Có | settings.index |
| GET | /api/settings/{key} | Lấy một key (nếu public hoặc có quyền) | Tùy key | settings.show (nếu private) |
| PUT | /api/settings | Cập nhật toàn bộ (body: object key-value) | Có | settings.update |
| PATCH | /api/settings | Cập nhật một phần (body: object key-value) | Có | settings.update |

### 3.2 Response mẫu

**GET /api/settings/public:**
```json
{
  "success": true,
  "data": {
    "general": {
      "copyright": "© 2026 QuânDH",
      "designed_by": "QuânDH",
      "language": "vi",
      "time_format": "H:i:s d/m/Y",
      "icon": "/storage/media/1/favicon.ico",
      "logo": "/storage/media/2/logo.png"
    },
    "admin_page": {
      "admin_app_name": "QuânDH Core",
      "admin_logo_title": "Hệ thống quản trị"
    },
    "social": {
      "social_facebook": "https://facebook.com/...",
      "social_email": "contact@example.com"
    }
  }
}
```

**GET /api/settings (authenticated):**
```json
{
  "success": true,
  "data": {
    "general": { ... },
    "admin_page": { ... },
    "org_select_page": { ... },
    "social": { ... },
    "api": { ... },
    "email": { ... },
    "sms": { ... },
    "zalo": { ... },
    "chat": { ... },
    "log": { ... }
  }
}
```

---

## 4. Module cấu trúc (gợi ý)

```
app/Modules/Core/
├── Models/
│   └── Setting.php
├── Services/
│   └── SettingService.php
├── Controllers/
│   └── SettingController.php
├── Requests/
│   └── UpdateSettingRequest.php
├── Resources/
│   └── SettingResource.php (hoặc SettingCollection)
├── Enums/
│   └── SettingGroupEnum.php
└── Routes/
    └── setting.php
```

**SettingService** cần:
- `getPublic()`: Trả về cấu hình công khai, nhóm theo group.
- `getAll()`: Trả về toàn bộ cấu hình (cho admin).
- `getByKey(string $key)`: Lấy một key, kiểm tra public hoặc auth.
- `update(array $data)`: Cập nhật nhiều key, validate theo từng key, dùng transaction.

---

## 5. Danh sách key chi tiết (cho Seeder)

### 5.1 General (general)

| key | value (default) | type | is_public |
|-----|-----------------|------|-----------|
| copyright | '' | string | true |
| designed_by | '' | string | true |
| language | vi | string | true |
| time_format | H:i:s d/m/Y | string | true |
| icon | null | string | true |
| logo | null | string | true |

### 5.2 Admin page (admin_page)

| key | value (default) | type | is_public |
|-----|-----------------|------|-----------|
| admin_app_name | QuânDH Core | string | true |
| admin_logo_title | Hệ thống quản trị | string | true |
| admin_welcome_title | Chào mừng đến với hệ thống | string | true |
| admin_app_description | '' | text | true |
| admin_background_image | null | string | true |

### 5.3 Org select page (org_select_page)

| key | value (default) | type | is_public |
|-----|-----------------|------|-----------|
| org_select_title | Chọn tổ chức | string | true |
| org_select_description | '' | text | true |
| org_select_background_image | null | string | true |

### 5.4 Social (social)

| key | value (default) | type | is_public |
|-----|-----------------|------|-----------|
| social_facebook | null | string | true |
| social_twitter | null | string | true |
| social_youtube | null | string | true |
| social_tiktok | null | string | true |
| social_gmail | null | string | true |
| social_email | null | string | true |

### 5.5 API (api) – is_public = false

| key | value (default) | type | is_public |
|-----|-----------------|------|-----------|
| api_gemini_url | null | string | false |
| api_gemini_token | null | string | false |
| api_deepseek_url | null | string | false |
| api_deepseek_token | null | string | false |
| api_chatgpt_url | null | string | false |
| api_chatgpt_token | null | string | false |
| firebase_service_account | null | json | false |
| api_firebase_enabled | false | boolean | false |
| api_google_maps_url | null | string | false |
| api_google_maps_token | null | string | false |

### 5.6 Email (email) – is_public = false

| key | value (default) | type | is_public |
|-----|-----------------|------|-----------|
| email_protocol | smtp | string | false |
| email_sender_name | '' | string | false |
| email_sender_address | null | string | false |
| email_smtp_host | null | string | false |
| email_smtp_username | null | string | false |
| email_smtp_password | null | string | false |
| email_smtp_port | 587 | string | false |
| email_smtp_encryption | tls | string | false |
| email_test_address | null | string | false |

### 5.7 SMS (sms) – is_public = false

| key | value (default) | type | is_public |
|-----|-----------------|------|-----------|
| sms_server | null | string | false |
| sms_username | null | string | false |
| sms_password | null | string | false |
| sms_test_phone | null | string | false |

### 5.8 Zalo (zalo) – is_public = false

| key | value (default) | type | is_public |
|-----|-----------------|------|-----------|
| zalo_server | null | string | false |
| zalo_username | null | string | false |
| zalo_password | null | string | false |
| zalo_sender | null | string | false |
| zalo_template_id | null | string | false |
| zalo_extra_params | null | json | false |

### 5.9 Chat nội bộ (chat) – is_public = false

| key | value (default) | type | is_public |
|-----|-----------------|------|-----------|
| chat_server | null | string | false |
| chat_api_key | null | string | false |
| chat_sender | null | string | false |
| chat_receiver | null | string | false |
| chat_room | null | string | false |
| chat_message | null | string | false |
| chat_department | null | string | false |
| chat_email_title | null | string | false |
| chat_test_type | null | string | false |

### 5.10 Log (log) – is_public = false

| key | value (default) | type | is_public |
|-----|-----------------|------|-----------|
| log_retention_days | 90 | integer | false |

---

## 6. Cấu hình nhật ký định kỳ (Log retention)

- **log_retention_days:** Số ngày giữ nhật ký. Job định kỳ (scheduler) sẽ xóa các bản ghi trong `log_activities` có `created_at` cũ hơn `now() - log_retention_days`.
- Cần tạo **Console Command** hoặc **Scheduled Task**: `LogActivityCleanup` chạy hàng ngày.
- Đọc giá trị từ `Setting::get('log_retention_days', 90)` trong command.

---

## 7. Cache

- Cache key: `settings.public` và `settings.all` với TTL phù hợp (vd: 3600 giây).
- Khi cập nhật settings qua API, clear cache tương ứng.
- Service nên có helper `Setting::get($key, $default)` và `Setting::getCached()`.

---

## 8. Permission cần bổ sung

Trong `PermissionSeeder`:

```php
'settings' => [
    'index', 'show', 'update',
],
```

- `settings.index`: Xem toàn bộ cấu hình.
- `settings.show`: Xem chi tiết (có thể gộp với index).
- `settings.update`: Cập nhật cấu hình.

---

## 9. LogActivity & Media

- **LogActivity:** Cập nhật `LogActivity` middleware: resource = `settings`, action = `update`, mô tả ví dụ: "Cập nhật cấu hình hệ thống".
- **Media:** Các trường ảnh (icon, logo, background) có thể lưu `media_id` hoặc URL. Nếu dùng MediaService, lưu ID và resolve khi trả API.

---

*Tài liệu phục vụ triển khai module Setting trong Core.*
