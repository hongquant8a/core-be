# API Cấu hình (Setting) – Core

> Cập nhật lần cuối: 16:02:55 15/07/2026 — bổ sung endpoint `available-channels`, viết lại đầy đủ 17 group settings theo `SettingSeeder.php`, thêm mô tả hành vi type `password`/`image`.

Quản lý cấu hình hệ thống: lấy cấu hình công khai, lấy toàn bộ (cần auth), lấy danh sách kênh thông báo khả dụng, cập nhật.

**Base path:** `/api/settings`

---

## Lấy cấu hình công khai

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/settings/public` |
| **Auth** | Không cần |
| **Response** | Object nhóm theo group (general, admin_page, org_select_page, social). Chỉ các key có is_public = true. |

**Ví dụ response:**
```json
{
  "success": true,
  "data": {
    "general": {
      "copyright": "© 2026 QuânDH",
      "designed_by": "QuânDH",
      "language": "vi",
      "time_format": "H:i:s d/m/Y",
      "icon": null,
      "logo": null,
      "app_name": "QuânDH Core"
    },
    "admin_page": {
      "admin_logo_title": "Hệ thống quản trị"
    },
    "social": {
      "social_facebook": null,
      "social_email": null
    }
  }
}
```

---

## Lấy toàn bộ cấu hình

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/settings` |
| **Auth** | Bearer token, permission: settings.index |
| **Response** | Object nhóm theo group — đầy đủ **17 group** (xem bảng bên dưới). |

**Danh sách đầy đủ group & key** (nguồn: `database/seeders/SettingSeeder.php`):

| Group | Key | Type | Ghi chú |
|---|---|---|---|
| `general` | `copyright`, `designed_by`, `language`, `time_format`, `icon`, `logo`, `contact_email`, `organization_name`, `app_name`, `app_description`, `app_title` | string/image/text | `icon`, `logo` là `image`. |
| `admin_page` | `admin_logo_title`, `admin_background_image` | string/image | `admin_app_name`/`admin_app_description`/`admin_welcome_title` cũ đã chuyển sang `general.app_name`/`app_description` (đang chờ dọn dẹp DB). |
| `org_select_page` | `org_select_title`, `org_select_background_image` | string/image | |
| `social` | `social_facebook`, `social_twitter`, `social_youtube`, `social_tiktok`, `social_gmail`, `social_email` | string | |
| `api` | `api_gemini_url`, `api_gemini_token`, `api_deepseek_url`, `api_deepseek_token`, `api_chatgpt_url`, `api_chatgpt_token`, `api_google_maps_url`, `api_google_maps_token` | string | Không public. |
| `notification` | `fcm_enabled`, `firebase_service_account`, `firebase_private_vapid_key`, `firebase_api_key`, `firebase_auth_domain`, `firebase_project_id`, `firebase_storage_bucket`, `firebase_messaging_sender_id`, `firebase_app_id`, `firebase_vapid_key` | boolean/json/string | FCM/Firebase (BE Admin SDK + FE Web SDK). Các key `firebase_api_key…firebase_vapid_key` là public (FE cần để init Firebase SDK); `fcm_enabled`, `firebase_service_account`, `firebase_private_vapid_key` không public. |
| `email` | `email_enabled`, `email_protocol`, `email_sender_name`, `email_sender_address`, `email_smtp_host`, `email_smtp_username`, `email_smtp_password`, `email_smtp_port`, `email_smtp_encryption`, `email_test_address` | boolean/string | |
| `sms` | `sms_enabled`, `sms_server`, `sms_username`, `sms_password`, `sms_test_phone` | boolean/string | |
| `zalo` | `zalo_enabled`, `zalo_app_id`, `zalo_app_secret`, `zalo_access_token`, `zalo_refresh_token` | boolean/string | Zalo OA — gửi tin nhắn tự do qua Official Account API v2.0. Channel key khi gửi noti: `zalo`. |
| `zalo_zns` | `zns_enabled`, `zns_server`, `zns_username`, `zns_password`, `zns_sender`, `zns_template_id`, `zns_extra_params`, `zns_sms_failover_sender`, `zns_sms_failover_unicode` | boolean/string/json | Zalo ZNS — gửi theo template đã duyệt, qua relay WorldSMS (`POST https://api-04.worldsms.vn/apidebit/sendZNS`, Basic Auth). Channel key: `zalo_zns`. Có SMS failover tùy chọn nếu ZNS thất bại. |
| `telegram` | `tg_enabled`, `tg_bot_token` | boolean/string | |
| `chat` | `chat_enabled`, `chat_server`, `chat_api_key`, `chat_sender`, `chat_receiver`, `chat_room`, `chat_message`, `chat_department`, `chat_email_title`, `chat_test_type` | boolean/string | |
| `log` | `log_retention_days` | integer | |
| `sso_danang` | `sso_danang_enabled`, `sso_danang_base_url`, `sso_danang_client_id`, `sso_danang_client_secret`, `sso_danang_redirect_uri`, `sso_danang_scope` | boolean/string | `sso_danang_client_secret` không public. Xem `docs/api/sso.md`. |
| `sso_cbccvc` | `sso_cbccvc_enabled`, `sso_cbccvc_base_url` | boolean/string | Xem `docs/api/sso.md`. |
| `auth` | `auth_auto_create_default_role_id` | integer | **Không public.** ⚠️ Hiện KHÔNG được dùng ở bất kỳ đâu trong code (`grep -r "auth_auto_create_default_role_id" app/` ra rỗng) — thiết kế auto-create user qua SSO đã bị gỡ bỏ (xem `docs/api/sso.md`). Coi như dead setting. |
| `security` | `system_password` | string | Mật khẩu hệ thống (super password). Seed mặc định khai `type=string`, nhưng migration `2026_06_06_000002_add_system_password_setting.php` tạo record ban đầu với `type=password` — type thực tế phụ thuộc thứ tự migrate/seed đã chạy trên môi trường. Xem hành vi `type=password` bên dưới. |

---

## Lấy danh sách kênh thông báo khả dụng

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/settings/available-channels` |
| **Auth** | Không cần (route không có middleware `permission`). |
| **Response** | Mảng các kênh notification đang bật, tính từ cờ `*_enabled` tương ứng trong settings: `email_enabled` → `mail`, `fcm_enabled` → `fcm`, `sms_enabled` → `sms`, `zalo_enabled` → `zalo`, `zns_enabled` → `zalo_zns`, `tg_enabled` → `telegram`. |

**Ví dụ response:**
```json
{
  "success": true,
  "data": [
    { "value": "mail", "title": "Email", "label": "Email" },
    { "value": "fcm", "title": "Thông báo đẩy (App)", "label": "Thông báo đẩy (App)" },
    { "value": "zalo_zns", "title": "Zalo ZNS", "label": "Zalo ZNS" }
  ]
}
```

---

## Lấy một key

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/settings/{key}` |
| **Auth** | Bearer token, permission: settings.show |
| **UrlParam** | key – Key cấu hình (vd: copyright, log_retention_days). |
| **Response** | Object { key, value, group, label, type }. |

---

## Cập nhật cấu hình

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/settings` |
| **Auth** | Bearer token, permission: settings.update |
| **Body** | Object key-value. Chỉ cập nhật các key tồn tại. Các key nhạy cảm (password, token) không lưu vào log. |
| **Response** | Toàn bộ cấu hình sau khi cập nhật. |

**Ví dụ body:**
```json
{
  "copyright": "© 2026 QuânDH",
  "language": "vi",
  "log_retention_days": 90,
  "admin_logo_title": "Hệ thống quản trị"
}
```

**Hành vi field `type=image`** (`icon`, `logo`, `admin_background_image`, `org_select_background_image`):
- Gửi `multipart/form-data` kèm file blob → upload ảnh mới qua `MediaService` (xóa ảnh cũ trước), trả về URL mới dạng `/storage/{media_id}/{file_name}`.
- Gửi `application/json` với value là string URL (ảnh cũ) → **giữ nguyên**, không thay đổi.
- Gửi `application/json` với value là chuỗi rỗng `""` (hoặc `null`) → xóa ảnh, giá trị trả về thành `null`.

**Hành vi field `type=password`** (vd `system_password`, nếu type thực tế trong DB là `password`):
- Khi cập nhật: giá trị gửi lên được **hash bằng bcrypt** (`Hash::make`) trước khi lưu, không lưu plaintext.
- Gửi chuỗi rỗng `""` (hoặc falsy) → xóa mật khẩu (set `null`).
- Khi đọc (`GET /api/settings`, `/api/settings/{key}`): nếu đã có giá trị, trả về **mask cố định `••••••`** thay vì hash thật; nếu chưa cấu hình, trả `null`.
