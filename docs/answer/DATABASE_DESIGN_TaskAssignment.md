# DATABASE DESIGN — Module TaskAssignment

Giao việc liên phòng ban.

**Lưu ý:** Module đa tổ chức — model dùng `HasOrganizationScope`, mọi query tự động scope theo `organization_id` hiện tại từ middleware `set.permissions.team`. Phòng ban được quản lý riêng qua bảng `task_assignment_departments`.

---

### `task_assignment_departments`
Phòng ban nội bộ phục vụ nghiệp vụ giao việc.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| code | varchar(255) | No | — | UNIQUE |
| name | varchar(255) | No | — | |
| description | text | Yes | null | |
| status | varchar(255) | No | 'active' | active, inactive |
| sort_order | int unsigned | No | 0 | |
| is_petition_overview | boolean | No | false | Nhận đơn thư tổng hợp |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id, INDEX |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `task_assignment_employees`
Nhân viên module Task — lớp gate giữa `users` tổng và pivot phòng ban. Chỉ user nằm trong bảng này (status=active) mới được gán vào dept/task.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| user_id | bigint unsigned | No | — | FK → users.id CASCADE |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id nullOnDelete, INDEX |
| status | varchar(255) | No | 'active' | active, inactive. INDEX |
| note | text | Yes | null | Ghi chú nội bộ |
| created_by | bigint unsigned | Yes | null | FK → users.id nullOnDelete |
| updated_by | bigint unsigned | Yes | null | FK → users.id nullOnDelete |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(user_id, organization_id) → `ta_employees_user_org_unique`.

Backfill: migration insert DISTINCT (user_id, organization_id) từ `task_assignment_users` hiện có → mọi thành viên dept đang có tự động thành nhân viên active.

### `task_assignment_types`
Loại văn bản giao việc.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| name | varchar(255) | No | — | |
| description | text | Yes | null | |
| status | varchar(255) | No | 'active' | active, inactive |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id, INDEX |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `task_assignment_item_types`
Loại công việc. Cấu trúc giống `task_assignment_types` (có `organization_id`).

### `task_assignment_documents`
Văn bản giao việc.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| name | varchar(255) | No | — | |
| summary | text | Yes | null | |
| issue_date | date | Yes | null | INDEX |
| task_assignment_type_id | bigint unsigned | Yes | null | FK → task_assignment_types.id, INDEX |
| status | varchar(255) | No | 'draft' | draft, issued. INDEX |
| issued_at | timestamp | Yes | null | |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id, INDEX |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `task_assignment_document_attachments`
Tệp đính kèm văn bản giao việc.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_document_id | bigint unsigned | No | — | FK → task_assignment_documents.id CASCADE |
| media_id | bigint unsigned | No | — | FK → media.id CASCADE |
| file_name | varchar(255) | Yes | null | |
| sort_order | int unsigned | No | 0 | |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(task_assignment_document_id, media_id)

### `task_assignment_items`
Công việc thuộc văn bản.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_document_id | bigint unsigned | No | — | FK CASCADE, INDEX |
| name | varchar(255) | No | — | |
| description | text | Yes | null | |
| task_assignment_item_type_id | bigint unsigned | Yes | null | FK nullOnDelete, INDEX |
| deadline_type | varchar(255) | No | 'no_deadline' | has_deadline, no_deadline |
| start_at | datetime | Yes | null | |
| end_at | datetime | Yes | null | INDEX(deadline_type, end_at) |
| processing_status | varchar(255) | No | 'todo' | todo, in_progress, pending_approval, done, paused, cancelled. INDEX |
| completion_percent | tinyint unsigned | No | 0 | 0-100 |
| rejection_reason | text | Yes | null | Lý do từ chối (khi reject từ pending_approval → todo) |
| reported_at | datetime | Yes | null | Thời điểm nộp báo cáo đạt 100% |
| reported_by | bigint unsigned | Yes | null | FK → users.id (người nộp báo cáo) |
| priority | varchar(255) | No | 'medium' | low, medium, high, urgent. INDEX |
| completed_at | datetime | Yes | null | |
| approved_by | bigint unsigned | Yes | null | FK → users.id (người duyệt hoàn thành) |
| assigned_by | bigint unsigned | Yes | null | FK → users.id (người giao việc) |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id, INDEX |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `task_assignment_item_user`
Pivot: công việc ↔ người dùng (đã bao gồm cả department).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_item_id | bigint unsigned | No | — | FK CASCADE |
| department_id | bigint unsigned | No | — | FK CASCADE |
| user_id | bigint unsigned | No | — | FK CASCADE |
| assignment_role | varchar(255) | No | 'main' | main, support |
| assignment_status | varchar(255) | No | 'assigned' | assigned, accepted, rejected, done |
| assigned_at | datetime | Yes | null | |
| accepted_at | datetime | Yes | null | |
| completed_at | datetime | Yes | null | |
| note | text | Yes | null | |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(task_assignment_item_id, user_id), INDEX(department_id, assignment_status)

### `task_assignment_item_attachments`
Tệp đính kèm công việc.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_item_id | bigint unsigned | No | — | FK → task_assignment_items.id CASCADE |
| media_id | bigint unsigned | No | — | FK → media.id CASCADE |
| file_name | varchar(255) | Yes | null | |
| sort_order | unsigned int | No | 0 | |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `task_assignment_item_reports`
Báo cáo kết quả thực hiện công việc.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_item_id | bigint unsigned | No | — | FK CASCADE |
| reporter_user_id | bigint unsigned | Yes | null | FK nullOnDelete (người nộp báo cáo) |
| completion_percent | unsigned tinyint | Yes | null | Tiến độ tại thời điểm báo cáo (0-100) |
| completed_at | datetime | Yes | null | INDEX |
| report_document_number | varchar(255) | Yes | null | |
| report_document_excerpt | text | Yes | null | |
| report_document_content | text | Yes | null | |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id, INDEX |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

INDEX(task_assignment_item_id, reporter_user_id)

### `task_assignment_item_report_attachments`
Tệp đính kèm báo cáo.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_item_report_id | bigint unsigned | No | — | FK CASCADE |
| media_id | bigint unsigned | No | — | FK CASCADE |
| file_name | varchar(255) | Yes | null | |
| sort_order | int unsigned | No | 0 | |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(task_assignment_item_report_id, media_id)

### `task_assignment_reminders`
Nhắc việc tự động (per-record). Có thể gắn vào document hoặc item.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_document_id | bigint unsigned | Yes | null | FK → task_assignment_documents.id CASCADE |
| reminder_type | varchar(255) | No | 'scheduled' | instant, scheduled |
| task_assignment_item_id | bigint unsigned | Yes | null | FK CASCADE |
| notification_schedule_id | bigint unsigned | Yes | null | FK → notification_schedules.id |
| moment | varchar(10) | Yes | null | before, on, after |
| offset_minutes | unsigned int | No | 0 | |
| channels | json | Yes | null | Kênh gửi (system, email, zalo, sms) |
| source | varchar(255) | No | 'PRESET' | PRESET, CUSTOM |
| remind_at | datetime | Yes | null | |
| sent_at | datetime | Yes | null | |
| channel | varchar(255) | No | — | system, email, zalo, sms |
| recipient_department_id | bigint unsigned | Yes | null | FK nullOnDelete |
| recipient_user_id | bigint unsigned | Yes | null | FK nullOnDelete |
| status | enum | No | 'pending' | pending, fired, cancelled, active |
| error_message | text | Yes | null | |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `task_assignment_petitions`
Đơn thư (phòng ban).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| department_id | bigint unsigned | Yes | null | FK → task_assignment_departments.id nullOnDelete |
| submission_date | date | No | — | Ngày nộp |
| deadline_date | date | Yes | null | Hạn xử lý |
| sender_name | varchar(255) | No | — | Tên người gửi |
| sender_address | varchar(500) | Yes | null | Địa chỉ |
| sender_cccd | varchar(20) | Yes | null | CCCD |
| sender_phone | varchar(30) | Yes | null | SĐT |
| sender_email | varchar(255) | Yes | null | Email |
| content | text | Yes | null | Nội dung đơn |
| processing_status | varchar(30) | No | 'new' | new, processing, done |
| completed_at | datetime | Yes | null | |
| document_number | varchar(255) | Yes | null | Số hiệu văn bản trả lời |
| document_excerpt | text | Yes | null | Trích yếu |
| response_content | text | Yes | null | Nội dung trả lời |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id nullOnDelete |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

INDEX: department_id, processing_status, submission_date, organization_id.

### `task_assignment_petition_attachments`
Tệp đính kèm đơn thư.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| petition_id | bigint unsigned | No | — | FK → task_assignment_petitions.id CASCADE |
| media_id | bigint unsigned | No | — | FK → media.id CASCADE |
| file_name | varchar(255) | Yes | null | |
| type | varchar(255) | Yes | null | Phân loại attachment |
| sort_order | unsigned int | No | 0 | |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(petition_id, media_id)

### Sơ đồ quan hệ (Module TaskAssignment)

```
task_assignment_types ──1-n──► task_assignment_documents ──1-n──► task_assignment_reminders
                                    ├── 1-n ──► task_assignment_document_attachments ──► media
                                    └── 1-n ──► task_assignment_items
                                                    ├── n-n ──► task_assignment_item_user ◄── users, task_assignment_departments
                                                    ├── 1-n ──► task_assignment_item_attachments ──► media
                                                    ├── 1-n ──► task_assignment_item_reports
                                                    │               └── 1-n ──► task_assignment_item_report_attachments ──► media
                                                    └── 1-n ──► task_assignment_reminders

task_assignment_item_types ──1-n──► task_assignment_items

task_assignment_departments
    ├── 1-n ──► task_assignment_employees ◄── users
    ├── 1-n ──► task_assignment_petitions ──► task_assignment_petition_attachments ──► media
    └── n-n ──► task_assignment_item_user
```

---

*File được cập nhật theo migration trong `database/migrations/`.*
