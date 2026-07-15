# Domain Glossary — QLCV

> Ngày tạo: 00:00:00 28/06/2026  
> Cập nhật lần cuối: 00:00:00 28/06/2026

Map thuật ngữ nghiệp vụ tiếng Việt ↔ tên class/bảng trong code. Dùng khi không chắc tên class của một khái niệm nghiệp vụ, hoặc khi cần đặt tên mới phù hợp convention.

---

## Module Core

| Thuật ngữ nghiệp vụ | Model / Class | Bảng DB | Ghi chú |
|---|---|---|---|
| Tổ chức / UBND | `Organization` | `organizations` | Tenant root, có cây phân cấp |
| Người dùng | `User` | `users` | 1 user có thể thuộc nhiều org |
| Vai trò | `Role` (Spatie) | `roles` | Gắn với 1 org qua teams mode |
| Quyền | `Permission` (Spatie) | `permissions` | Format: `{resource}.{action}` |
| Phòng ban | `Department` | `departments` | Cây phòng ban theo org |
| Nhật ký hoạt động | `LogActivity` | `log_activities` | Mỗi request tạo 1 row |
| Cài đặt hệ thống | `Setting` | `settings` | Key-value theo org |
| Thông báo | `Notification` | `notifications` | Header, xem chi tiết ở Notification module |
| Kênh gửi thông báo | `NotificationDelivery` | `notification_deliveries` | 1 notification N deliveries |

## Module TaskAssignment

| Thuật ngữ nghiệp vụ | Model / Class | Bảng DB | Ghi chú |
|---|---|---|---|
| Văn bản / Công việc giao | `TaskAssignment` | `task_assignments` | Root — 1 văn bản giao nhiều đầu việc |
| Đầu việc / Hạng mục | `TaskAssignmentItem` | `task_assignment_items` | Đơn vị thực thi nhỏ nhất |
| Phòng ban thực hiện | `TaskAssignmentDepartment` | `task_assignment_departments` | Phòng ban được giao trong 1 đầu việc |
| Cán bộ thực hiện | `TaskAssignmentEmployee` | `task_assignment_employees` | Cá nhân thực hiện đầu việc |
| Loại công việc | `TaskAssignmentType` | `task_assignment_types` | Danh mục phân loại |
| Loại đầu việc | `TaskAssignmentItemType` | `task_assignment_item_types` | Danh mục phân loại đầu việc |
| Nhắc nhở deadline | `TaskAssignmentReminder` | `task_assignment_reminders` | Lập lịch nhắc, cron xử lý |
| Tài liệu đính kèm | `TaskAssignmentDocument` | `task_assignment_documents` | File đính kèm công việc |
| Kiến nghị / Đề xuất | `TaskAssignmentPetition` | `task_assignment_petitions` | Yêu cầu điều chỉnh từ assignee |
| Mức độ ưu tiên | `TaskAssignmentPriority` | `task_assignment_priorities` | Danh mục mức độ |
| Người dùng trong org | `TaskAssignmentUser` | `task_assignment_users` | User - Organization mapping |

## Module Meeting

| Thuật ngữ nghiệp vụ | Model / Class | Bảng DB | Ghi chú |
|---|---|---|---|
| Cuộc họp | `Meeting` | `meetings` | Root record |
| Phòng họp | `MeetingRoom` | `meeting_rooms` | Danh mục phòng vật lý |
| Thành phần dự họp | `MeetingParticipant` | `meeting_participants` | Người được mời |
| Chương trình nghị sự | `MeetingAgenda` | `meeting_agendas` | Danh sách mục họp |
| Biểu quyết | `MeetingVote` | `meeting_votes` | Vote trong 1 agenda |
| Kết quả biểu quyết | `MeetingVoteResult` | `meeting_vote_results` | 1 vote/participant |
| RSVP / Xác nhận tham dự | `MeetingRsvp` | `meeting_rsvps` | Phản hồi của participant |
| Loại cuộc họp | `MeetingType` | `meeting_types` | Danh mục |
| Ghi chú cá nhân | `MeetingPersonalNote` | `meeting_personal_notes` | Ghi chú riêng trong cuộc họp |
| Khách mời bên ngoài | `MeetingGuest` | `meeting_guests` | Người không có tài khoản |
| Điểm danh QR | `MeetingAttendance` | `meeting_attendances` | Check-in qua QR |
| Tài liệu họp | `MeetingDocument` | `meeting_documents` | File đính kèm cuộc họp |

## Module Scheduling (Lịch công tác)

| Thuật ngữ nghiệp vụ | Model / Class | Bảng DB | Ghi chú |
|---|---|---|---|
| Lịch công tác | `Schedule` | `schedules` | Lịch công tác theo đơn vị |
| Mục lịch | `ScheduleItem` | `schedule_items` | Từng hoạt động trong lịch |

## Notification Engine

| Thuật ngữ nghiệp vụ | Class | Ghi chú |
|---|---|---|
| Bộ điều phối thông báo | `NotificationDispatcher` | `app/Services/Notification/` — xuyên module |
| Cấu hình thông báo | `NotificationEventConfig` | Kênh + template theo loại event |
| Lịch thông báo | `NotificationSchedule` | Bật/tắt thông báo theo loại event / org |
| Kênh Zalo OA | `ZaloNotificationChannel` | Custom channel class |
| Kênh FCM | `FcmNotificationChannel` | Firebase Cloud Messaging |

---

## Quy tắc đặt tên mới

Khi thêm resource mới vào module, đặt tên theo pattern:

- **Model / Class:** `{ModulePascalCase}{ResourcePascalCase}` — ví dụ: `TaskAssignmentItemType`
- **Bảng DB:** `{module_snake_case}_{resource_snake_case}s` — ví dụ: `task_assignment_item_types`
- **Route prefix:** `{module-kebab-case}-{resource-kebab-case}s` — ví dụ: `task-assignment-item-types`
- **Permission:** `{module-kebab}-{resource-kebab}s.{action}` — ví dụ: `task-assignment-item-types.index`

Bảng pivot module: `{module_snake}_{resource_a_snake}_{resource_b_snake}` — ví dụ: `meeting_meeting_room`.
