# DATABASE DESIGN — Module Core

Hệ thống nền tảng: người dùng, tổ chức, phân quyền, thông báo, cấu hình, nhật ký.

---

## 1. Bảng `organizations`

Tổ chức (tenant). Mỗi user thuộc ít nhất 1 tổ chức. Spatie Permission scope theo team.

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `name` | varchar(255) | No | | Tên tổ chức |
| `code` | varchar(50) | Yes | NULL | Mã viết tắt |
| `parent_id` | bigint | Yes | NULL | FK → organizations.id (cấu trúc cây) |
| `status` | varchar(50) | No | 'active' | active / inactive |
| `sort_order` | int | No | 0 | |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |

---

## 2. Bảng `users`

Người dùng hệ thống. Dùng `HasRoles`, `HasPermissions` của Spatie (guard `web`).

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `name` | varchar(255) | No | | Họ tên |
| `email` | varchar(255) | No | unique | |
| `user_name` | varchar(255) | No | unique | Username đăng nhập |
| `password` | varchar(255) | No | | |
| `phone` | varchar(20) | Yes | NULL | BC: booted route sang user_profiles |
| `zalo_user_id` | varchar(100) | Yes | NULL | ID tài khoản Zalo OA |
| `gender` | varchar(10) | Yes | NULL | |
| `birth_date` | date | Yes | NULL | |
| `citizen_id` | varchar(20) | Yes | NULL | |
| `permanent_address` | text | Yes | NULL | |
| `temporary_address` | text | Yes | NULL | |
| `status` | varchar(50) | No | 'active' | active / inactive |
| `priority_weight` | int | Yes | NULL | Thứ tự hiển thị dropdown chủ trì |
| `email_verified_at` | datetime | Yes | NULL | |
| `last_login_at` | datetime | Yes | NULL | |
| `created_by` | bigint | Yes | NULL | FK → users.id |
| `updated_by` | bigint | Yes | NULL | FK → users.id |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |

### Phân quyền (Spatie Permission)

Bảng chuẩn Spatie + `globalize_roles_table`:

| Bảng | Chức năng |
|---|---|
| `permissions` | Danh sách permission (guard `web`) |
| `roles` | Danh sách role (có `organization_id`: null = global, có value = scoped) |
| `model_has_permissions` | Permission gán trực tiếp cho User |
| `model_has_roles` | Role gán cho User (hỗ trợ team) |
| `role_has_permissions` | Permission trong Role |

---

## 3. Bảng `user_profiles`

Hồ sơ mở rộng của user (tách khỏi users để giảm clutter).

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `user_id` | bigint FK | No | → users.id |
| `phone` | varchar(20) | Yes | Số điện thoại chính |
| `position_name` | varchar(255) | Yes | Chức vụ |
| `department_name` | varchar(255) | Yes | Phòng ban |
| `avatar_media_id` | bigint | Yes | FK → media.id |
| `created_at` | datetime | Yes | |
| `updated_at` | datetime | Yes | |

---

## 4. Bảng `fcm_tokens`

Push notification tokens của user.

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `user_id` | bigint FK | No | → users.id |
| `token` | text | No | FCM device token |
| `device_type` | varchar(50) | Yes | web / android / ios |
| `created_at` | datetime | Yes | |
| `updated_at` | datetime | Yes | |

---

## 5. Bảng `user_socials`

Đăng nhập mạng xã hội (SSO).

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `user_id` | bigint FK | No | → users.id |
| `provider` | varchar(50) | No | google / facebook / zalo |
| `provider_id` | varchar(255) | No | ID từ provider |
| `provider_token` | text | Yes | |
| `created_at` | datetime | Yes | |
| `updated_at` | datetime | Yes | |

---

## 6. Bảng `user_preferences`

Tuỳ chỉnh cá nhân của user.

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `user_id` | bigint FK | No | → users.id |
| `key` | varchar(100) | No | Key setting |
| `value` | text | Yes | JSON value |
| `created_at` | datetime | Yes | |
| `updated_at` | datetime | Yes | |

---

## 7. Bảng `settings`

Cấu hình hệ thống (key-value có group).

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `key` | varchar(191) | No | unique | |
| `value` | text | Yes | NULL | |
| `group` | varchar(50) | No | 'general' | general / zalo / email / notification |
| `type` | varchar(50) | Yes | 'string' | string / json / boolean / integer |
| `description` | varchar(255) | Yes | NULL | |

---

## 8. Bảng `media`

Spatie Media Library — quản lý file upload toàn hệ thống.

| Column | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `model_type` | varchar(255) | Polymorphic |
| `model_id` | bigint | Polymorphic |
| `collection_name` | varchar(255) | Tên collection |
| `name` | varchar(255) | Tên hiển thị |
| `file_name` | varchar(255) | Tên file trên disk |
| `mime_type` | varchar(127) | |
| `disk` | varchar(127) | Disk name (public, s3, ...) |
| `size` | bigint | Kích thước (bytes) |
| ... | | (các cột khác của Spatie) |

---

## 9. Bảng `log_activities`

Nhật ký hoạt động (middleware `log.activity`).

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `organization_id` | bigint | Yes | |
| `user_id` | bigint | Yes | → users.id |
| `action` | varchar(100) | No | Tên hành động |
| `resource` | varchar(100) | Yes | Resource bị tác động |
| `resource_id` | bigint | Yes | ID resource |
| `details` | text | Yes | JSON chi tiết |
| `ip_address` | varchar(45) | Yes | |
| `user_agent` | text | Yes | |
| `created_at` | datetime | Yes | |

---

## 10. Hệ thống Notification (Core)

Dùng chung cho tất cả module (Meeting, TaskAssignment, Scheduling).

### 10.1. `notification_event_configs`

Cấu hình bật/tắt từng loại sự kiện thông báo theo module + org.

| Column | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `module_key` | varchar(50) | meeting / task_assignment / scheduling |
| `organization_id` | bigint FK | → organizations.id |
| `event_key` | varchar(100) | Key sự kiện (vd: schedule_published) |
| `enabled` | boolean | true/false |

### 10.2. `notification_schedules`

Cấu hình lịch gửi của từng event (instant hoặc định kỳ).

| Column | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `notification_event_config_id` | bigint FK | → notification_event_configs.id |
| `moment` | varchar(20) | before / on / after / null (= instant) |
| `offset_minutes` | int | Số phút (chỉ dùng khi moment ≠ null) |
| `channels` | json | ["fcm","mail","zalo","zalo_zns","sms"] |
| `label` | varchar(255) | Tên hiển thị |
| `sort_order` | int | 0 |

### 10.3. `notifications`

Bản ghi thông báo đã gửi (log).

| Column | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `organization_id` | bigint FK | → organizations.id |
| `user_id` | bigint FK | → users.id (người nhận) |
| `event_key` | varchar(100) | Key sự kiện |
| `notifiable_type` | varchar(255) | Morph type (Schedule, Meeting, ...) |
| `notifiable_id` | bigint | Morph id |
| `title` | varchar(500) | Tiêu đề |
| `body` | text | Nội dung |
| `context` | json | Payload thêm (link, data) |
| `read_at` | datetime | NULL nếu chưa đọc |
| `created_at` | datetime | |

### 10.4. `notification_deliveries`

Trạng thái gửi của từng kênh cho mỗi notification.

| Column | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `notification_id` | bigint FK | → notifications.id |
| `channel` | varchar(30) | fcm / mail / zalo / zalo_zns / sms |
| `status` | varchar(20) | pending / sent / failed |
| `sent_at` | datetime | Thời điểm gửi |
| `error_message` | text | Lỗi nếu failed |
| `created_at` | datetime | |

### 10.5. `notification_templates`

Template thông báo theo kênh (Zalo ZNS, SMS) — mapping template_id ↔ module/event/channel.

| Column | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `organization_id` | bigint FK | → organizations.id nullOnDelete |
| `module_key` | varchar(50) | meeting / task_assignment / scheduling |
| `event_key` | varchar(100) | Key sự kiện (nullable) |
| `channel` | varchar(30) | zalo_zns (mặc định) |
| `template_id` | varchar(255) | ID template trên kênh |
| `variable_mapping` | json | Map biến → field dữ liệu |
| `is_default` | boolean | false |
| `status` | varchar(20) | active / inactive |
| `created_by` / `updated_by` | bigint | FK → users.id |
| `created_at` / `updated_at` | datetime | |

UNIQUE: (organization_id, module_key, channel)

---

## Sơ đồ quan hệ Core (rút gọn)

```
organizations (1) ──── (N) users
                              │
          ┌───────────────────┼───────────────────┐
          │                   │                   │
   user_profiles         fcm_tokens         user_socials
    (1:1)                 (1:N)              (1:N)

organizations (1) ──── (N) notification_event_configs ──── (N) notification_schedules
organizations (1) ──── (N) notification_templates
organizations (1) ──── (N) notifications ──── (N) notification_deliveries
```
