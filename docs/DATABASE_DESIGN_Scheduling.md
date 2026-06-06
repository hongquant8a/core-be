# DATABASE DESIGN — Module Scheduling (Lịch công tác)

Quản lý lịch công tác tuần: EXECUTIVE (Thường trực) và OFFICE (Văn phòng).

---

## 1. Bảng `schedules`

Lịch công tác chính.

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint FK | No | | → organizations.id |
| `module_type` | enum | No | 'OFFICE' | EXECUTIVE / OFFICE |
| `session` | enum/varchar | No | 'S' | S (Sáng) / C (Chiều) / T (Tối) |
| `date_time` | datetime | Yes | NULL | Ngày giờ sự kiện (dev: `date_time`, prod cũ: `date`) |
| `content` | text | Yes | NULL | Nội dung lịch |
| `location` | varchar(500) | Yes | NULL | Địa điểm |
| `host_id` | bigint FK | Yes | NULL | → users.id (chủ trì) |
| `host_text` | varchar(255) | Yes | NULL | Tên chủ trì (nếu không có user) |
| `driver_id` | bigint FK | Yes | NULL | → users.id (lái xe) |
| `driver_text` | varchar(255) | Yes | NULL | Tên lái xe |
| `preparation_unit` | varchar(500) | Yes | NULL | Đơn vị chuẩn bị |
| `departments_text` | text | Yes | NULL | Phòng ban tham gia (text) |
| `participants_text` | text | Yes | NULL | Thành phần tham dự (text) |
| `participant_count` | varchar(50) | Yes | NULL | Số lượng người tham dự |
| `nature` | enum | No | 'HOST' | HOST (chủ trì) / ATTEND (tham dự) |
| `is_important` | boolean | No | false | Đánh dấu lịch quan trọng |
| `status` | int | No | 0 | 0=DRAFT, 1=PUBLISHED |
| `approval_status` | varchar(20) | Yes | NULL | NULL=không cần duyệt, pending=đợi duyệt, approved=đã duyệt, rejected=từ chối |
| `sort_order` | int | No | 0 | Thứ tự trong ngày + buổi |
| `week_number` | int | Yes | NULL | ISO week (auto từ date_time) |
| `year` | int | Yes | NULL | ISO year (auto từ date_time) |
| `approved_by` | bigint FK | Yes | NULL | → users.id |
| `approved_at` | datetime | Yes | NULL | |
| `created_by` | bigint FK | Yes | NULL | → users.id |
| `updated_by` | bigint FK | Yes | NULL | → users.id |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |
| `deleted_at` | datetime | Yes | NULL | Soft delete |

**Indexes:**
- `idx_org_module_datetime` (organization_id, module_type, date_time, session, status)
- `idx_org_host_datetime` (organization_id, host_id, date_time)
- `idx_org_driver_datetime` (organization_id, driver_id, date_time)
- `idx_sort_datetime` (organization_id, date_time, session, sort_order)

**Status (publish status):** `0 DRAFT → 1 PUBLISHED`, có thể quay lại DRAFT.

**Approval status:** `null → pending → approved` hoặc `pending → rejected → pending`. Chỉ áp dụng khi `org_scheduling_settings.requires_approval = true`.

---

## 2. Bảng `schedule_attachments`

File đính kèm của lịch.

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `schedule_id` | bigint FK | No | → schedules.id |
| `title` | varchar(255) | Yes | Tên hiển thị |
| `file_name` | varchar(255) | No | Tên file |
| `file_path` | varchar(500) | No | Đường dẫn storage |
| `mime_type` | varchar(100) | Yes | |
| `file_size` | bigint | Yes | Bytes |
| `sort_order` | int | No | 0 |
| `uploaded_by` | bigint FK | Yes | → users.id |
| `created_at` | datetime | Yes | |

---

## 3. Bảng `schedule_notification_recipients`

Người nhận thông báo của lịch (hoặc cá nhân hoặc nhóm).

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `schedule_id` | bigint FK | No | → schedules.id |
| `user_id` | bigint FK | Yes | → users.id (cá nhân) |
| `group_id` | bigint FK | Yes | → scheduling_employee_groups.id (nhóm) |
| `display_name` | varchar(255) | Yes | Tên hiển thị |
| `created_at` | datetime | Yes | |

---

## 4. Bảng `schedule_reminders`

Nhắc lịch per-item (tầng 3). User chọn khi tạo/sửa từng schedule.

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `schedule_id` | bigint FK | No | | → schedules.id |
| `moment` | enum | No | 'before' | immediate / before / on / after |
| `offset_minutes` | int | Yes | 0 | Số phút offset (có nghĩa với before & after) |
| `remind_at` | datetime | Yes | NULL | Thời điểm fire thực tế (tính khi publish) |
| `notification_schedule_id` | bigint FK | Yes | NULL | → notification_schedules.id (nếu dùng preset) |
| `status` | enum | No | 'pending' | pending / fired / cancelled |
| `fired_at` | datetime | Yes | NULL | Thời điểm cron đã fire |
| `channels` | json | No | [] | ["fcm","mail","zalo"] |
| `source` | varchar(50) | No | 'CUSTOM' | CUSTOM / PRESET |
| `created_at` | datetime | Yes | | |

**moment:** `immediate` = bắn ngay khi schedule được publish; `before`/`on`/`after` = tính từ event_time + offset_minutes.

---

## 5. Bảng `schedule_notifications`

Queue thông báo đã được tạo ra cho schedule (instant + reminder).

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `organization_id` | bigint | Yes | |
| `schedule_id` | bigint FK | No | → schedules.id |
| `user_id` | bigint FK | No | → users.id |
| `channel` | varchar(50) | No | app / fcm / mail / zalo / zalo_zns / sms |
| `remind_at` | datetime | No | Thời điểm gửi |
| `status` | int | No | 0=PENDING, 1=SENT, 2=FAILED, 3=CANCELLED |
| `retry_count` | int | No | 0 |
| `error_message` | text | Yes | |
| `created_at` | datetime | Yes | |

---

## 6. Bảng `schedule_participants`

Người tham dự lịch (backup/reference, không dùng chính trong code hiện tại).

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `schedule_id` | bigint FK | No | → schedules.id |
| `organization_id` | bigint FK | No | → organizations.id |
| `user_id` | bigint FK | Yes | → users.id |
| `display_name` | varchar(255) | Yes | |
| `position_name` | varchar(255) | Yes | Chức vụ |
| `is_external` | boolean | No | false |
| `sort_order` | int | No | 0 |

---

## 7. Bảng `scheduling_employees`

Nhân viên trong module lịch công tác (chỉ user trong bảng này mới được chọn làm chủ trì).

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `organization_id` | bigint FK | No | → organizations.id |
| `user_id` | bigint FK | No | → users.id |
| `name` | varchar(255) | No | |
| `position_name` | varchar(255) | Yes | Chức vụ |
| `department` | varchar(255) | Yes | Phòng ban |
| `email` | varchar(255) | Yes | |
| `phone` | varchar(20) | Yes | |
| `priority_weight` | int | No | 0 | Trọng số ưu tiên |
| `status` | varchar(50) | No | 'active' | active / inactive |
| `sort_order` | int | No | 0 |
| `created_by` | bigint FK | Yes | → users.id |
| `updated_by` | bigint FK | Yes | → users.id |
| `created_at` | datetime | Yes | |
| `updated_at` | datetime | Yes | |
| `deleted_at` | datetime | Yes | Soft delete |

---

## 8. Bảng `scheduling_employee_groups` + `scheduling_employee_group_members`

Nhóm nhân viên lịch công tác (N:N).

| `scheduling_employee_groups` | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `organization_id` | bigint FK | → organizations.id |
| `name` | varchar(255) | |
| `description` | varchar(500) | |
| `status` | varchar(50) | active / inactive |
| `sort_order` | int | |
| `created_by` | bigint FK | → users.id |
| `updated_by` | bigint FK | → users.id |
| `created_at` | datetime | |
| `updated_at` | datetime | |
| `deleted_at` | datetime | Soft delete |

| `scheduling_employee_group_members` | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `scheduling_employee_group_id` | bigint FK | → scheduling_employee_groups.id |
| `scheduling_employee_id` | bigint FK | → scheduling_employees.id |

---

## 9. Bảng `scheduling_settings`

Cấu hình chung module lịch công tác.

| Column | Type | Nullable | Ghi chú |
|---|---|---|---|
| `id` | bigint PK | No | |
| `organization_id` | bigint FK | No | → organizations.id (unique) |
| `approval_enabled` | boolean | No | false | Bật duyệt lịch |
| `approval_module_types` | json | Yes | [] | Module type cần duyệt |
| `default_channels` | json | Yes | ["inapp"] | Kênh thông báo mặc định |
| `working_sessions` | json | Yes | | {"MORNING":{"start":"07:30","end":"11:30"},...} |
| `created_at` | datetime | Yes | |
| `updated_at` | datetime | Yes | |

---

## 10. Bảng `org_scheduling_settings`

Cấu hình duyệt lịch đơn giản (per org).

| Column | Type | Nullable | Default | Ghi chú |
|---|---|---|---|---|
| `id` | bigint PK | No | auto | |
| `organization_id` | bigint FK | No | unique | → organizations.id |
| `requires_approval` | boolean | No | false | Bật = lịch vào PENDING thay vì PUBLISHED |
| `created_at` | datetime | Yes | NULL | |
| `updated_at` | datetime | Yes | NULL | |

---

## 11. Bảng `scheduling_filter_presets`

Bộ lọc nhanh đã lưu.

| Column | Type | Ghi chú |
|---|---|---|
| `id` | bigint PK | |
| `organization_id` | bigint FK | → organizations.id |
| `user_id` | bigint FK | → users.id |
| `name` | varchar(255) | Tên bộ lọc |
| `filters` | json | JSON filter params |
| `is_default` | boolean | false | Đánh dấu mặc định cho user |
| `created_at` | datetime | |
| `updated_at` | datetime | |

---

## Sơ đồ quan hệ Scheduling

```
schedules (1) ──── (N) schedule_attachments
             ──── (N) schedule_notification_recipients
             ──── (N) schedule_reminders
             ──── (N) schedule_notifications
             ──── (N) schedule_participants

scheduling_employees (N) ──── (N) scheduling_employee_groups
                         └── scheduling_employee_group_members

org_scheduling_settings (1:1) ──── organizations
scheduling_settings (1:1) ──── organizations
```

## Hệ thống thông báo Scheduling (3 tầng)

| Tầng | Bảng | Mô tả |
|---|---|---|
| 1 | `notification_event_configs` | Bật/tắt notification cho module scheduling |
| 2 | `notification_schedules` | Cấu hình lịch gửi cho từng event (moment + offset_minutes + channels) |
| 3 | `schedule_reminders` | Nhắc lịch per-item khi tạo/sửa schedule |
