# Sơ đồ thiết kế cơ sở dữ liệu (TỔNG QUAN)

> Ngày tạo: 00:00:00 16/06/2026  
> Cập nhật lần cuối: 12:00:00 16/07/2026

Tài liệu index — chi tiết từng module xem file riêng.

---

## Cấu trúc module

```
app/Modules/
├── Core/            Nền tảng: users, orgs, roles, permissions, settings, notification config, logs
├── Meeting/         Phòng họp không giấy
├── TaskAssignment/  Giao việc liên phòng ban
├── Scheduling/      Lịch công tác tuần (EXECUTIVE / OFFICE)
└── Beneficiary/     Người có công — hồ sơ + đối tượng + thân nhân + tài liệu
```

> **Beneficiary v2** (dựng lại ngày 15/08/2026, thay cho bản cũ theo trục hộ gia đình) — thiết kế và lý do từng quyết định ở [answer/module-nguoi-co-cong-thiet-ke-lai-v2_110031_15082026.md](../answer/module-nguoi-co-cong-thiet-ke-lai-v2_110031_15082026.md).

---

## Tài liệu chi tiết

| Module | File | Bảng chính |
|---|---|---|
| **Core** | [Core.md](Core.md) | users, organizations, roles, permissions, settings, media, log_activities, notifications, notification_event_configs, notification_schedules, notification_templates, chat_conversations, chat_messages |
| **TaskAssignment** | [TaskAssignment.md](TaskAssignment.md) | task_assignment_items, task_assignment_types, task_assignment_departments, task_assignment_documents, task_assignment_petitions ... |
| **Meeting** | [Meeting.md](Meeting.md) | meetings, meeting_agendas, meeting_documents, meeting_participants, meeting_vote_topics, meeting_discussion_registrations, meeting_attendees ... |
| **Scheduling** | [Scheduling.md](Scheduling.md) | schedules, schedule_reminders, schedule_notifications, scheduling_employees, org_scheduling_settings ... |
| **Beneficiary** | [Beneficiary.md](Beneficiary.md) | beneficiaries, beneficiary_type_relations, beneficiary_dependents, beneficiary_documents, beneficiary_residential_areas, beneficiary_types, beneficiary_relationships |

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

Bảng **không** có `organization_id`: `permissions` (global), `media` (polymorphic), `notification_event_configs` (scoped by org riêng), `meeting_minutes_templates` (dùng chung toàn hệ thống).

---

## Ghi chú migration đặc biệt

- Bảng `user_socials` đã bị xóa (migration 2026-06-06).
- Cột `working_sessions` trong `scheduling_settings` đã được tách thành `executive_working_sessions` và `office_working_sessions` trong `org_scheduling_settings` (migration 2026-06-08).
- Cột `allow_host_management` đã được chuyển từ `meeting_settings` sang `meetings` (migration 2026-06-18).
- Cột `is_voting_result_hidden_until_end` và `is_vote_change_allowed` đã được chuyển từ `meetings` sang `meeting_vote_topics` (migration 2026-06-18).
- Cột `meetings.auto_confirm_attendance` và `meetings.internal_chat_enabled` thêm mới (migration 2026-08-10). Bảng `chat_conversations`/`chat_messages` (Core) tạo mới cùng ngày — engine chat dùng chung cho DM toàn hệ thống và chat nhóm theo cuộc họp.
