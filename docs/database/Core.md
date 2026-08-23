# DATABASE DESIGN — Module Core

> Ngày tạo: 00:00:00 16/06/2026  
> Cập nhật lần cuối: 13:43:16 10/08/2026

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

Dùng package `spatie/laravel-permission` với chế độ **Teams** (xem `config/permission.php`: `teams = true`, `team_foreign_key = organization_id`). Guard duy nhất là `web` (áp dụng cho cả API Sanctum).

Migration gốc: `2026_02_17_000001_create_permission_tables.php`.  
Migration toàn cục hóa role: `2026_02_28_180000_globalize_roles_table.php` — gộp role trùng tên về một bản duy nhất, set `organization_id = null` cho tất cả role → **role luôn là global**, không scoped theo tổ chức.

---

#### Bảng `permissions`

Lưu danh sách quyền. Custom thêm 3 cột so với Spatie chuẩn (`description`, `sort_order`, `parent_id`) để tổ chức thành **cây 3 tầng**.

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `name` | varchar(255) | No | | Tên permission — xem quy ước đặt tên bên dưới |
| `guard_name` | varchar(255) | No | | Luôn là `web` |
| `description` | text | Yes | NULL | Nhãn tiếng Việt mô tả quyền |
| `sort_order` | unsignedInt | No | 0 | Thứ tự hiển thị trong cây |
| `parent_id` | unsignedBigInt | Yes | NULL | FK → permissions.id (nullOnDelete) |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |

UNIQUE: (`name`, `guard_name`)

**Quy ước đặt tên permission — cây 3 tầng:**

| Tầng | Định dạng | Ví dụ |
|---|---|---|
| 1 — nhóm module | `module:{Module}` | `module:Core`, `module:TaskAssignment`, `module:Meeting`, `module:Scheduling` |
| 2 — nhóm resource | `group:{resource}` | `group:users`, `group:roles`, `group:meetings` |
| 3 — action | `{resource}.{action}` | `users.index`, `meetings.store`, `schedules-executive.approve` |

Chỉ tầng 3 được dùng để kiểm tra quyền (`can()` / `hasPermissionTo()`). Tầng 1 và 2 là nhóm hiển thị trong UI quản lý phân quyền.

---

#### Bảng `roles`

Lưu danh sách vai trò. Sau migration toàn cục hóa, `organization_id` **luôn là NULL** — role không scoped theo tổ chức.

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint | Yes | NULL | FK → organizations.id (nullOnDelete) — **luôn NULL** sau globalize |
| `name` | varchar(255) | No | | Tên vai trò |
| `guard_name` | varchar(255) | No | | Luôn là `web` |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |

UNIQUE: (`name`, `guard_name`)

**Vai trò mặc định (seed bởi `PermissionSeeder`):**

| Vai trò | Mô tả |
|---|---|
| `Super Admin` | Toàn quyền hệ thống |
| `Admin` | Toàn quyền hệ thống (tương đương Super Admin) |
| `Quản trị` | Quản lý danh mục + xem/thống kê văn bản công việc |
| `Trưởng phòng` | Tạo/giao văn bản, công việc; xem báo cáo phòng mình |
| `Nhân viên` | Xem công việc được giao, cập nhật tiến độ, tạo báo cáo |
| `Tổng hợp lịch` | Toàn quyền module Lịch công tác |
| `Thư ký` | Full quyền Lịch Thường trực (schedules-executive) |
| `Văn phòng` | Xem + duyệt Lịch Văn phòng (schedules-office) |
| `Lái xe` | Chỉ xem màn hình phân công lái xe (driver-view) |

---

#### Bảng `model_has_permissions`

Permission gán trực tiếp lên User (không qua Role).

| Column | Type | Ghi chú |
|---|---|---|
| `permission_id` | bigint | FK → permissions.id cascadeOnDelete |
| `model_type` | varchar(255) | Morph type (`App\Modules\Core\Models\User`) |
| `model_id` | bigint | Morph id (user.id) |
| `organization_id` | bigint | FK team scope (Spatie Teams) — trỏ tới tổ chức hiện tại khi gán |

PRIMARY KEY: (`organization_id`, `permission_id`, `model_id`, `model_type`)

---

#### Bảng `model_has_roles`

Role gán cho User trong phạm vi tổ chức (Spatie Teams).

| Column | Type | Ghi chú |
|---|---|---|
| `role_id` | bigint | FK → roles.id cascadeOnDelete |
| `model_type` | varchar(255) | Morph type (`App\Modules\Core\Models\User`) |
| `model_id` | bigint | Morph id (user.id) |
| `organization_id` | bigint | FK team scope — User có thể có role khác nhau ở mỗi tổ chức |

PRIMARY KEY: (`organization_id`, `role_id`, `model_id`, `model_type`)

> Đây là nơi kiểm soát "user X có role Y trong tổ chức Z". Khi gán role phải `setPermissionsTeamId($org->id)` trước.

---

#### Bảng `role_has_permissions`

Permission trong Role (pivot thuần, không có team scope).

| Column | Type | Ghi chú |
|---|---|---|
| `permission_id` | bigint | FK → permissions.id cascadeOnDelete |
| `role_id` | bigint | FK → roles.id cascadeOnDelete |

PRIMARY KEY: (`permission_id`, `role_id`)

---

## 3. Bảng `user_profiles`

Hồ sơ mở rộng của user (tách khỏi users để giảm clutter).

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `user_id` | bigint FK | No | → users.id |
| `phone` | varchar(20) | Yes | Số điện thoại chính |
| `telegram_chat_id` | varchar(255) | Yes | Chat ID Telegram (dùng cho thông báo Telegram) |
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

## 5. Bảng `user_preferences`

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

## 6. Bảng `settings`

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

## 7. Bảng `media`

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

## 8. Bảng `log_activities`

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
| `device_id` | varchar(100) | Yes | Header `X-Device-Id` — cùng nguồn với `fcm_tokens.device_id`, client tự sinh nên chỉ dùng để đối chiếu |
| `created_at` | datetime | Yes | |

---

## 9. Hệ thống Notification (Core)

Dùng chung cho tất cả module (Meeting, TaskAssignment, Scheduling).

### 9.1. `notification_event_configs`

Cấu hình bật/tắt từng loại sự kiện thông báo theo module + tổ chức.

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `module_key` | varchar(50) | Yes | meeting / task_assignment / scheduling. NULL = áp dụng cho mọi module |
| `organization_id` | bigint FK | No | → organizations.id cascadeOnDelete |
| `event_key` | varchar(50) | No | Key sự kiện (vd: `schedule_published`) |
| `enabled` | boolean | No | default false |
| `created_at` / `updated_at` | datetime | Yes | |

UNIQUE: (`module_key`, `event_key`, `organization_id`)

### 9.2. `notification_schedules`

Cấu hình lịch gửi của từng event (child của `notification_event_configs`). Channels nằm ở đây, không phải ở event_config.

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `notification_event_config_id` | bigint FK | Yes | → notification_event_configs.id cascadeOnDelete |
| `moment` | ENUM('before','on','after') | Yes | NULL = instant (fire ngay khi sự kiện xảy ra) |
| `offset_minutes` | unsignedInt | Yes | Số phút offset; chỉ dùng khi moment ≠ null |
| `channels` | json | Yes | ["fcm","mail","zalo","zalo_zns","sms"] |
| `label` | varchar(255) | No | Tên hiển thị |
| `sort_order` | int | No | 0 |
| `created_at` / `updated_at` | datetime | Yes | |

### 9.3. `notifications`

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

### 9.4. `notification_deliveries`

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

### 9.5. `notification_templates`

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

### 9.6. `reminders`

> Migration: `2026_06_28_000001_create_reminders_table.php`

Bảng nhắc lịch **thống nhất** (polymorphic) thay thế 3 bảng riêng lẻ cũ (`task_assignment_reminders`, `meeting_reminders`, `schedule_reminders`) bị drop ngày 28/06/2026. Áp dụng cho Meeting, TaskAssignmentItem, Schedule.

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `remindable_type` | varchar(255) | No | | Morph type: `…TaskAssignment\Models\TaskAssignmentItem` / `…Meeting\Models\Meeting` / `…Scheduling\Models\Schedule` |
| `remindable_id` | bigint | No | | Morph id |
| `organization_id` | bigint FK | Yes | NULL | → organizations.id cascadeOnDelete |
| `reminder_type` | varchar(20) | No | 'scheduled' | `scheduled` = fire theo remind_at; `manual` = admin tạo thủ công (chỉ Meeting) |
| `source` | varchar(10) | No | 'PRESET' | `PRESET` = lấy channels từ notification_schedule; `CUSTOM` = dùng cột channels |
| `notification_schedule_id` | bigint FK | Yes | NULL | → notification_schedules.id nullOnDelete |
| `moment` | varchar(10) | Yes | NULL | `before` / `on` / `after` / null (= instant) |
| `offset_minutes` | int | Yes | NULL | Số phút offset |
| `remind_at` | datetime | Yes | NULL | Thời điểm fire thực tế (tính từ moment + offset) |
| `channels` | json | Yes | NULL | Kênh gửi — dùng khi source = CUSTOM |
| `status` | varchar(20) | No | 'pending' | `pending` / `fired` / `cancelled` |
| `fired_at` | datetime | Yes | NULL | Thời điểm đã fire |
| `message` | text | Yes | NULL | Chỉ dùng cho Meeting manual reminder |
| `created_by` | bigint FK | Yes | NULL | → users.id nullOnDelete (chỉ meeting manual) |
| `created_at` / `updated_at` | datetime | Yes | NULL | |

Indexes:
- `idx_remindable` (`remindable_type`, `remindable_id`)
- `idx_status_remind_at` (`status`, `remind_at`) — cron job query chính
- `idx_org_status` (`organization_id`, `status`)

---

## 10. Chat nội bộ (`chat_conversations` / `chat_messages`)

Engine chat dùng chung cho 2 loại hội thoại, đặt trong Core (`App\Modules\Core\Models`) vì
được cả module Meeting (chat nhóm theo cuộc họp) và tính năng nhắn tin riêng toàn hệ thống
tái sử dụng. Xem thêm [docs/modules/Chat/README.md](../modules/Chat/README.md).

### `chat_conversations`

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint unsigned | No | — | FK → organizations.id CASCADE |
| `type` | varchar(255) | No | 'direct' | `direct` (DM 1-1) hoặc `meeting_group` (chat nhóm theo cuộc họp) |
| `meeting_id` | bigint unsigned | Yes | NULL | FK → meetings.id CASCADE — chỉ set khi type=meeting_group. UNIQUE (1 meeting = 1 conversation) |
| `user_one_id` | bigint unsigned | Yes | NULL | Chỉ dùng cho type=direct — luôn là `min(user_a, user_b)` |
| `user_two_id` | bigint unsigned | Yes | NULL | Chỉ dùng cho type=direct — luôn là `max(user_a, user_b)` |
| `created_by` | bigint unsigned | Yes | NULL | FK → users.id |
| `created_at` / `updated_at` | timestamp | Yes | NULL | `updated_at` được `touch()` mỗi khi có tin nhắn mới → dùng để sort "gần đây nhất" |

UNIQUE: `meeting_id`; `(organization_id, user_one_id, user_two_id)`.  
INDEX: `(organization_id, type)`.

**Lưu ý:**
- `type=meeting_group` **không** có bảng participant riêng — quyền truy cập derive động qua `Meeting::userMeetingRole($user)` (chair/operator/participant hiện tại), không lưu snapshot thành viên. Conversation được tạo lazy (`firstOrCreate`) ở lần gửi/xem tin nhắn đầu tiên, và bị chặn tạo nếu `meetings.internal_chat_enabled = false`.
- `type=direct`: không có bảng participant — chính 2 cột `user_one_id`/`user_two_id` (luôn normalize thứ tự) đóng vai trò định danh cặp hội thoại, đảm bảo unique index không tạo trùng dù gọi API theo thứ tự nào.

### `chat_messages`

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint unsigned | No | — | FK → organizations.id CASCADE |
| `chat_conversation_id` | bigint unsigned | No | — | FK → chat_conversations.id CASCADE |
| `sender_user_id` | bigint unsigned | No | — | FK → users.id (không ràng buộc DB, check qua service) |
| `content` | text | No | — | Chỉ text, chưa hỗ trợ đính kèm file/ảnh (v1) |
| `created_at` / `updated_at` | timestamp | Yes | NULL | |

INDEX: `(organization_id, chat_conversation_id, created_at)`.

**Không hỗ trợ sửa/xoá từng tin nhắn** (kể cả sender) — chỉ Super Admin xoá được **toàn bộ**
lịch sử của 1 conversation loại `meeting_group` qua `DELETE /api/meeting-chat-conversations/{id}`
(permission `meeting-chat-conversations.destroy`, chỉ gán role Super Admin — xem `PermissionSeeder`).

---

## Sơ đồ quan hệ Core (rút gọn)

```
organizations (1) ──── (N) users
                              │
          ┌───────────────────┼───────────────┐
          │                   │               │
   user_profiles         fcm_tokens     user_preferences
    (1:1)                 (1:N)              (1:N)

organizations (1) ──── (N) notification_event_configs ──── (N) notification_schedules
organizations (1) ──── (N) notification_templates
organizations (1) ──── (N) notifications ──── (N) notification_deliveries

reminders (polymorphic → TaskAssignmentItem | Meeting | Schedule)
  └── notification_schedule_id → notification_schedules
  [Thay thế task_assignment_reminders + meeting_reminders + schedule_reminders — đã drop 28/06/2026]

── Phân quyền (Spatie Permission + Teams) ──

permissions (cây 3 tầng: module → group → action)
  └── parent_id (self-reference)

roles (global, organization_id luôn NULL)
  └── role_has_permissions (N:N) ──── permissions

users (N:N với role, scoped theo organization)
  └── model_has_roles [organization_id, role_id, model_id, model_type]
  └── model_has_permissions [organization_id, permission_id, model_id, model_type]

── Chat nội bộ ──

chat_conversations (type=direct | meeting_group)
  ├── meeting_id → meetings.id (type=meeting_group, UNIQUE)
  ├── user_one_id / user_two_id → users.id (type=direct, cặp normalize)
  └── (1) ──── (N) chat_messages
```
