# DATABASE DESIGN — Module Scheduling (Lịch công tác)

> Ngày tạo: 00:00:00 16/06/2026  
> Cập nhật lần cuối: 00:00:00 29/06/2026

Quản lý lịch công tác tuần: EXECUTIVE (Thường trực) và OFFICE (Văn phòng).

---

## 1. Bảng `schedules`

Lịch công tác chính.

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint FK | No | | → organizations.id CASCADE |
| `module_type` | enum | No | 'OFFICE' | EXECUTIVE / OFFICE |
| `content` | text | Yes | NULL | Nội dung lịch |
| `location` | varchar(500) | Yes | NULL | Địa điểm |
| `nature` | enum | No | 'HOST' | HOST (chủ trì) / ATTEND (tham dự) |
| `participants_text` | text | Yes | NULL | Thành phần tham dự (text) |
| `departments_text` | text | Yes | NULL | Phòng ban tham gia (text) |
| `session` | enum | No | 'S' | S (Sáng) / C (Chiều) / T (Tối) |
| `date_time` | datetime | Yes | NULL | Ngày giờ sự kiện |
| `host_id` | bigint FK | Yes | NULL | → users.id nullOnDelete (chủ trì) |
| `host_text` | varchar(255) | Yes | NULL | Tên chủ trì (nếu không có user) |
| `driver_id` | bigint FK | Yes | NULL | → users.id nullOnDelete (lái xe) |
| `driver_text` | varchar(255) | Yes | NULL | Tên lái xe |
| `preparation_unit` | varchar(500) | Yes | NULL | Đơn vị chuẩn bị |
| `participant_count` | varchar(50) | Yes | NULL | Số lượng người tham dự |
| `status` | tinyint | No | 0 | 0=DRAFT, 1=PUBLISHED |
| `is_important` | boolean | No | false | Đánh dấu lịch quan trọng |
| `approval_status` | varchar(20) | Yes | NULL | NULL=không cần duyệt, pending=đợi duyệt, approved=đã duyệt, rejected=từ chối |
| `rejection_note` | text | Yes | NULL | Lý do từ chối |
| `sort_order` | unsignedSmallInteger | No | 0 | Thứ tự trong ngày + buổi |
| `week_number` | unsignedTinyInteger | No | 1 | ISO week (auto từ date_time) |
| `year` | unsignedSmallInteger | No | 2026 | ISO year (auto từ date_time) |
| `approved_by` | bigint FK | Yes | NULL | → users.id nullOnDelete |
| `approved_at` | datetime | Yes | NULL | |
| `created_by` | bigint FK | Yes | NULL | → users.id nullOnDelete |
| `updated_by` | bigint FK | Yes | NULL | → users.id nullOnDelete |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |
| `deleted_at` | datetime | Yes | NULL | Soft delete |

**Indexes:**
- `idx_org_module_datetime` (organization_id, module_type, date_time, session, status)
- `idx_org_week` (organization_id, year, week_number)
- `idx_org_host_datetime` (organization_id, host_id, date_time)
- `idx_org_driver_datetime` (organization_id, driver_id, date_time)
- `idx_sort_datetime` (organization_id, date_time, session, sort_order)
- (organization_id, approval_status)

**Status (publish status):** `0 DRAFT → 1 PUBLISHED`, có thể quay lại DRAFT.

**Approval status:** `null → pending → approved` hoặc `pending → rejected → pending`. Chỉ áp dụng khi `org_scheduling_settings` bật approval cho module type tương ứng.

---

## 2. Bảng `schedule_attachments`

File đính kèm của lịch.

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `schedule_id` | bigint FK | No | | → schedules.id CASCADE |
| `title` | varchar(255) | No | | Tên hiển thị |
| `file_name` | varchar(255) | No | | Tên file |
| `file_path` | varchar(500) | No | | Đường dẫn storage |
| `file_size` | bigint | No | | Bytes |
| `mime_type` | varchar(100) | No | | |
| `sort_order` | int | No | 0 | |
| `uploaded_by` | bigint FK | No | | → users.id CASCADE |
| `created_at` | datetime | Yes | NULL | |

---

## 3. Bảng `schedule_notification_recipients`

Người nhận thông báo của lịch (cá nhân hoặc nhóm).

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `schedule_id` | bigint FK | No | → schedules.id CASCADE |
| `user_id` | bigint FK | Yes | → users.id CASCADE (cá nhân) |
| `group_id` | bigint FK | Yes | → notification_groups.id CASCADE (nhóm) |
| `display_name` | varchar(255) | Yes | Tên hiển thị |
| `created_at` | datetime | Yes | |

---

## 4. ~~Bảng `schedule_reminders`~~ — **ĐÃ XÓA ngày 28/06/2026**

> Migration drop: `2026_06_28_000003_drop_old_reminder_tables.php`

Bảng này đã bị drop và thay thế bởi bảng `reminders` (polymorphic) trong Core.  
`remindable_type = 'App\Modules\Scheduling\Models\Schedule'`

Xem schema chi tiết tại [docs/database/Core.md](Core.md) — Mục 9.6 `reminders`.

---

## 5. Bảng `schedule_notifications`

Queue thông báo đã được tạo ra cho schedule (instant + reminder).

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint FK | No | | → organizations.id CASCADE |
| `schedule_id` | bigint FK | No | | → schedules.id CASCADE |
| `user_id` | bigint FK | No | | → users.id CASCADE |
| `channel` | enum | No | | FCM / ZALO / SMS / APP |
| `remind_at` | datetime | No | | Thời điểm gửi |
| `status` | tinyint | No | 0 | 0=PENDING, 1=SENT, 2=FAILED, 3=CANCELLED |
| `retry_count` | tinyint | No | 0 | |
| `external_message_id` | varchar(255) | Yes | NULL | ID từ kênh gửi bên ngoài |
| `error_message` | text | Yes | NULL | |
| `sent_at` | datetime | Yes | NULL | |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |

**Indexes:**
- `idx_org_status_remind` (organization_id, status, remind_at)
- `idx_schedule_id` (schedule_id)
- `idx_user_status` (user_id, status)

---

## 6. Bảng `scheduling_employees`

Nhân viên trong module lịch công tác (chỉ user trong bảng này mới được chọn làm chủ trì).

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint FK | No | | → organizations.id CASCADE |
| `user_id` | bigint FK | Yes | NULL | → users.id nullOnDelete |
| `name` | varchar(255) | No | | |
| `position_name` | varchar(255) | Yes | NULL | Chức vụ |
| `department` | varchar(255) | Yes | NULL | Phòng ban |
| `phone` | varchar(30) | Yes | NULL | |
| `email` | varchar(255) | Yes | NULL | |
| `priority_weight` | unsignedSmallInteger | No | 0 | Trọng số ưu tiên |
| `status` | varchar(30) | No | 'active' | active / inactive |
| `sort_order` | unsignedSmallInteger | No | 0 | |
| `created_by` | bigint FK | Yes | NULL | → users.id nullOnDelete |
| `updated_by` | bigint FK | Yes | NULL | → users.id nullOnDelete |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |
| `deleted_at` | datetime | Yes | NULL | Soft delete |

**Indexes:** `idx_org` (organization_id), `idx_org_status` (organization_id, status).

---

## 7. Bảng `scheduling_employee_groups` + `scheduling_employee_group_members`

Nhóm nhân viên lịch công tác (N:N).

| `scheduling_employee_groups` | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint FK | No | | → organizations.id CASCADE |
| `name` | varchar(255) | No | | |
| `description` | text | Yes | NULL | |
| `status` | varchar(30) | No | 'active' | active / inactive |
| `sort_order` | unsignedSmallInteger | No | 0 | |
| `created_by` | bigint FK | Yes | NULL | → users.id nullOnDelete |
| `updated_by` | bigint FK | Yes | NULL | → users.id nullOnDelete |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |
| `deleted_at` | datetime | Yes | NULL | Soft delete |

**Index:** `idx_org` (organization_id).

| `scheduling_employee_group_members` | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `scheduling_employee_group_id` | bigint FK | → scheduling_employee_groups.id CASCADE |
| `scheduling_employee_id` | bigint FK | → scheduling_employees.id CASCADE |

---

## 8. Bảng `scheduling_settings`

Cấu hình chung module lịch công tác (per org).

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint FK | No | unique | → organizations.id CASCADE |
| `default_channels` | json | Yes | NULL | Kênh thông báo mặc định |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |

**Lưu ý:** Cột `working_sessions` đã được tách thành `executive_working_sessions` và `office_working_sessions` trong bảng `org_scheduling_settings` (migration 2026-06-08).

---

## 9. Bảng `org_scheduling_settings`

Cấu hình duyệt lịch và giờ làm việc (per org, per module type).

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint FK | No | unique | → organizations.id CASCADE |
| `executive_requires_approval` | boolean | No | false | Bật duyệt cho EXECUTIVE |
| `office_requires_approval` | boolean | No | false | Bật duyệt cho OFFICE |
| `executive_working_sessions` | json | Yes | NULL | Giờ làm việc EXECUTIVE: {"MORNING":{"start":"07:30","end":"11:30"},...} |
| `office_working_sessions` | json | Yes | NULL | Giờ làm việc OFFICE: {"MORNING":{"start":"07:30","end":"11:30"},...} |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |

---

## 10. Bảng `scheduling_filter_presets`

Bộ lọc nhanh đã lưu.

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint FK | No | | → organizations.id CASCADE |
| `user_id` | bigint FK | No | | → users.id CASCADE |
| `name` | varchar(255) | No | | Tên bộ lọc |
| `filters` | json | No | | JSON filter params |
| `is_default` | boolean | No | false | Đánh dấu mặc định cho user |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |

**Index:** `idx_org_user` (organization_id, user_id).

---

## 11. Bảng `notification_groups`

Nhóm người nhận thông báo.

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `organization_id` | bigint FK | No | → organizations.id CASCADE |
| `name` | varchar(100) | No | |
| `description` | varchar(255) | Yes | |
| `created_by` | bigint FK | No | → users.id CASCADE |
| `created_at` | datetime | Yes | |
| `updated_at` | datetime | Yes | |

---

## 12. Bảng `notification_group_members`

Thành viên nhóm thông báo (N:N).

| Column | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `group_id` | bigint FK | → notification_groups.id CASCADE |
| `user_id` | bigint FK | → users.id CASCADE |

Ràng buộc: UNIQUE(group_id, user_id).

---

## 13. Bảng `reminder_presets` — **ORPHANED (không còn dùng)**

> Bảng được tạo: `2026_06_02_000001_finalize_scheduling_schema_standardization.php`

Bảng vẫn tồn tại trong DB nhưng không còn được tham chiếu bởi bất kỳ code nào sau khi `schedule_reminders` bị drop ngày 28/06/2026. Bảng `reminders` mới dùng `notification_schedule_id` thay vì `preset_id`.

**Không tạo mới hoặc đọc từ bảng này.** Có thể drop trong một migration dọn dẹp sau.

---

## Sơ đồ quan hệ Scheduling

```
schedules (1) ──── (N) schedule_attachments
             ──── (N) schedule_notification_recipients ──── notification_groups
             ──── (N) reminders [Core] (remindable_type = Schedule)
             ──── (N) schedule_notifications

scheduling_employees (N) ──── (N) scheduling_employee_groups
                         └── scheduling_employee_group_members

scheduling_settings (1:1) ──── organizations
org_scheduling_settings (1:1) ──── organizations
```

## Hệ thống thông báo Scheduling (3 tầng)

| Tầng | Bảng | Mô tả |
|---|---|---|
| 1 | `notification_event_configs` | Bật/tắt notification cho module scheduling theo org |
| 2 | `notification_schedules` | Cấu hình lịch gửi cho từng event (moment + offset_minutes + channels) |
| 3 | `reminders` [Core] | Nhắc lịch per-item khi tạo/sửa schedule (PRESET từ notification_schedule hoặc CUSTOM) |
