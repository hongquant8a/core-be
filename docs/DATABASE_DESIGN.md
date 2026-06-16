# Sơ đồ thiết kế cơ sở dữ liệu (TỔNG QUAN)

Tài liệu index — chi tiết từng module xem file riêng.

Cập nhật: 2026-06-16.

---

## Cấu trúc module

```
app/Modules/
├── Core/            Nền tảng: users, orgs, roles, permissions, settings, notification config, logs
├── Meeting/         Phòng họp không giấy
├── TaskAssignment/  Giao việc liên phòng ban
└── Scheduling/      Lịch công tác tuần (EXECUTIVE / OFFICE)
```

---

## Tài liệu chi tiết

| Module | File | Bảng chính |
|---|---|---|
| **Core** | [DATABASE_DESIGN_Core.md](DATABASE_DESIGN_Core.md) | users, organizations, roles, permissions, settings, media, log_activities, notifications, notification_event_configs, notification_schedules, notification_templates |
| **TaskAssignment** | [DATABASE_DESIGN_TaskAssignment.md](answer/DATABASE_DESIGN_TaskAssignment.md) | task_assignment_items, task_assignment_types, task_assignment_departments, task_assignment_documents ... |
| **Meeting** | [DATABASE_DESIGN_Meeting.md](answer/DATABASE_DESIGN_Meeting.md) | meetings, meeting_agendas, meeting_documents, meeting_participants, meeting_vote_topics, meeting_discussion_registrations, meeting_attendees ... |
| **Scheduling** | [DATABASE_DESIGN_Scheduling.md](DATABASE_DESIGN_Scheduling.md) | schedules, schedule_reminders, schedule_notifications, scheduling_employees ... |

---

## Notification — hệ thống dùng chung

Tất cả module dùng chung Core notification:

```
notification_event_configs (module_key + event_key + enabled)
    └── notification_schedules (moment + offset_minutes + channels)

notifications (log gửi)
    └── notification_deliveries (trạng thái từng kênh)
```

| module_key | Module |
|---|---|
| `meeting` | Meeting |
| `task_assignment` | TaskAssignment |
| `scheduling` | Scheduling |

Events per module được define trong `NotificationEventEnum` và `NotificationModuleEnum`.

---

## Tenant (đa tổ chức)

Hầu hết các bảng nghiệp vụ có `organization_id` FK → `organizations.id`. Global scope qua `TenantModel` (auto filter theo Spatie team context).

Bảng **không** có `organization_id`: `permissions` (global), `media` (polymorphic), `notification_event_configs` (scoped by org riêng).

---

## Ghi chú migration Scheduling

Bảng `schedules` có sự khác biệt schema giữa dev và production:
- **Dev:** column `date_time` (datetime)
- **Production cũ:** column `date` (date)

Code dùng `Schedule::dateColumn()` để tự động detect. Migration chuẩn hóa về `date_time` sẽ chạy khi deploy.
