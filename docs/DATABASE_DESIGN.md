# Sơ đồ thiết kế cơ sở dữ liệu

Tài liệu mô tả chi tiết cấu trúc các bảng trong hệ thống, đồng bộ với migration Laravel.

---

## 1. Người dùng & xác thực

### `users`
Bảng người dùng (Laravel Auth).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| name | varchar(255) | No | — | |
| email | varchar(255) | No | — | UNIQUE |
| user_name | varchar(100) | Yes | null | UNIQUE, dùng để đăng nhập cùng email |
| email_verified_at | timestamp | Yes | null | |
| password | varchar(255) | No | — | |
| remember_token | varchar(100) | Yes | null | |
| status | varchar(255) | No | 'active' | active, inactive, banned |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `user_preferences`
Tuỳ chọn người dùng (quan hệ **1–1** với `users`): lưu tổ chức làm việc gần nhất để lần đăng nhập sau backend trả `current_organization_id` đúng theo DB (nếu còn hợp lệ).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| user_id | bigint unsigned | No | — | UNIQUE, FK → users.id (cascade delete) |
| current_organization_id | bigint unsigned | Yes | null | FK → organizations.id (null on delete org) |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `password_reset_tokens`
Token đặt lại mật khẩu.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| email | varchar(255) | No | — | PK |
| token | varchar(255) | No | — | |
| created_at | timestamp | Yes | null | |

### `sessions`
Phiên đăng nhập (session).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | varchar(255) | No | — | PK |
| user_id | bigint unsigned | Yes | null | INDEX |
| ip_address | varchar(45) | Yes | null | |
| user_agent | text | Yes | null | |
| payload | longtext | No | — | |
| last_activity | int | No | — | INDEX |

### `personal_access_tokens`
Token API (Sanctum): tokenable_type, tokenable_id (morphs).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| tokenable_type | varchar(255) | No | — | Polymorphic |
| tokenable_id | bigint unsigned | No | — | Polymorphic, INDEX |
| name | text | No | — | |
| token | varchar(64) | No | — | UNIQUE |
| abilities | text | Yes | null | |
| last_used_at | timestamp | Yes | null | |
| expires_at | timestamp | Yes | null | INDEX |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

---

## 2. Cache & Queue (Laravel)

### `cache`
Cache key-value.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| key | varchar(255) | No | — | PK |
| value | mediumtext | No | — | |
| expiration | int | No | — | INDEX |

### `cache_locks`
Lock cho cache.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| key | varchar(255) | No | — | PK |
| owner | varchar(255) | No | — | |
| expiration | int | No | — | INDEX |

### `jobs`
Hàng đợi job.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| queue | varchar(255) | No | — | INDEX |
| payload | longtext | No | — | |
| attempts | tinyint unsigned | No | — | |
| reserved_at | int unsigned | Yes | null | |
| available_at | int unsigned | No | — | |
| created_at | int unsigned | No | — | |

### `job_batches`
Batch job (queue batching).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | varchar(255) | No | — | PK |
| name | varchar(255) | No | — | |
| total_jobs | int | No | — | |
| pending_jobs | int | No | — | |
| failed_jobs | int | No | — | |
| failed_job_ids | longtext | No | — | |
| options | mediumtext | Yes | null | |
| cancelled_at | int | Yes | null | |
| created_at | int | No | — | |
| finished_at | int | Yes | null | |

### `failed_jobs`
Job thất bại.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| uuid | varchar(255) | No | — | UNIQUE |
| connection | text | No | — | |
| queue | text | No | — | |
| payload | longtext | No | — | |
| exception | longtext | No | — | |
| failed_at | timestamp | No | current | |

---

## 3. Core – Permission, Role, Organization (Spatie Laravel Permission)

### `organizations`
Bảng tổ chức (organization) dùng cho Spatie Laravel Permission; cấu trúc cây theo parent_id.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| name | varchar(255) | No | — | |
| slug | varchar(255) | Yes | null | UNIQUE |
| description | text | Yes | null | |
| status | varchar(255) | No | 'active' | active, inactive |
| parent_id | bigint unsigned | Yes | null | FK → organizations.id (cha) |
| sort_order | int unsigned | No | 0 | Thứ tự trong cây |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `permissions`
Quyền (Spatie Laravel Permission). Bổ sung description, sort_order, parent_id để nhóm và sắp xếp hiển thị frontend.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| name | varchar(255) | No | — | UNIQUE(name, guard_name) |
| guard_name | varchar(255) | No | — | |
| description | text | Yes | null | Mô tả hiển thị frontend |
| sort_order | int unsigned | No | 0 | Thứ tự sắp xếp |
| parent_id | bigint unsigned | Yes | null | FK → permissions.id (nhóm cấp cha) |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `roles`
Vai trò (Spatie Laravel Permission, bật teams/organizations). Cấu trúc mặc định Spatie, không có cột status.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id (ngữ cảnh organization) |
| name | varchar(255) | No | — | UNIQUE(organization_id, name, guard_name) |
| guard_name | varchar(255) | No | — | |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `model_has_permissions`
Pivot: model (user) ↔ permission (Spatie, bật organizations).

| Cột | Kiểu | Ràng buộc / Ghi chú |
|-----|------|---------------------|
| permission_id | bigint unsigned | FK → permissions.id |
| model_type | varchar(255) | Polymorphic |
| model_id | bigint unsigned | Polymorphic |
| organization_id | bigint unsigned | FK organization (khi bật teams) |
| — | — | PK(organization_id, permission_id, model_id, model_type) |

### `model_has_roles`
Pivot: model (user) ↔ role (Spatie, bật organizations).

| Cột | Kiểu | Ràng buộc / Ghi chú |
|-----|------|---------------------|
| role_id | bigint unsigned | FK → roles.id |
| model_type | varchar(255) | Polymorphic |
| model_id | bigint unsigned | Polymorphic |
| organization_id | bigint unsigned | FK organization (khi bật teams) |
| — | — | PK(organization_id, role_id, model_id, model_type) |

### `role_has_permissions`
Pivot: role ↔ permission (Spatie).

| Cột | Kiểu | Ràng buộc / Ghi chú |
|-----|------|---------------------|
| permission_id | bigint unsigned | FK → permissions.id |
| role_id | bigint unsigned | FK → roles.id |
| — | — | PK(permission_id, role_id) |

### `log_activities`
Nhật ký truy cập của người dùng.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| description | varchar(255) | No | — | Mô tả hành động (vd: Xem chi tiết bài viết #10) |
| user_type | varchar(255) | No | 'Guest' | Loại user (Guest, User, ...) |
| user_id | bigint unsigned | Yes | null | FK → users.id |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id |
| route | varchar(255) | No | — | URL đầy đủ |
| method_type | varchar(255) | No | — | GET, POST, PUT, ... |
| status_code | int | No | — | 200, 400, 500, ... |
| ip_address | varchar(255) | No | — | |
| country | varchar(255) | Yes | null | |
| user_agent | text | Yes | null | |
| request_data | json | Yes | null | Dữ liệu request (đã loại trừ password, token) |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Quan hệ:** belongsTo user, organization. Index: user_id+created_at, organization_id+created_at, created_at.

### `settings`
Bảng cấu hình hệ thống (key-value): thông tin chung, trang quản trị, trang chọn tổ chức, mạng xã hội, API, nhật ký.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| key | varchar(255) | No | — | UNIQUE |
| value | text | Yes | null | Giá trị cấu hình |
| group | varchar(100) | No | 'general' | general, admin_page, org_select_page, social, api, email, sms, zalo, chat, log |
| is_public | boolean | No | true | true = trả về khi gọi API công khai |
| type | varchar(50) | No | 'string' | string, text, integer, boolean, json |
| label | varchar(255) | Yes | null | Nhãn hiển thị tiếng Việt |
| sort_order | int unsigned | No | 0 | Thứ tự hiển thị trong nhóm |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

**Quan hệ:** belongsTo creator, editor (User). Chi tiết các key mặc định và API xem `/docs/answer/phan-tich-bang-cau-hinh.md`.

### `media`
Bảng media dùng chung từ Spatie Media Library — quản lý file polymorphic cho nhiều model (TaskAssignment, Meeting, ...).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| model_type | varchar(255) | No | — | Polymorphic type |
| model_id | bigint unsigned | No | — | Polymorphic id |
| uuid | char(36) | Yes | null | UNIQUE |
| collection_name | varchar(255) | No | — | Ví dụ: `document-attachments`, `meeting-documents` |
| name | varchar(255) | No | — | Tên hiển thị |
| file_name | varchar(255) | No | — | Tên file lưu trên disk |
| mime_type | varchar(255) | Yes | null | |
| disk | varchar(255) | No | — | Disk lưu trữ (`public`) |
| conversions_disk | varchar(255) | Yes | null | |
| size | bigint unsigned | No | — | Kích thước (bytes) |
| manipulations | json | No | — | |
| custom_properties | json | No | — | Lưu metadata (vd `original_name`) |
| generated_conversions | json | No | — | |
| responsive_images | json | No | — | |
| order_column | int unsigned | Yes | null | Thứ tự trong collection |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

---

## 4. Giao việc liên phòng ban (Module TaskAssignment)

**Lưu ý:** Module đa tổ chức — model dùng `HasOrganizationScope`, mọi query tự động scope theo `organization_id` hiện tại từ middleware `set.permissions.team`. Phòng ban được quản lý riêng qua bảng `task_assignment_departments`.

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
| processing_status | varchar(255) | No | 'todo' | todo, in_progress, done, overdue, paused, cancelled. INDEX |
| completion_percent | tinyint unsigned | No | 0 | 0-100 |
| priority | varchar(255) | No | 'medium' | low, medium, high, urgent. INDEX |
| completed_at | datetime | Yes | null | |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id, INDEX |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### `task_assignment_item_department`
Pivot: công việc ↔ phòng ban.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_item_id | bigint unsigned | No | — | FK CASCADE |
| department_id | bigint unsigned | No | — | FK CASCADE |
| role | varchar(255) | No | 'main' | main, cooperate |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(task_assignment_item_id, department_id)

### `task_assignment_item_user`
Pivot: công việc ↔ người dùng.

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

### `task_assignment_item_reports`
Báo cáo kết quả thực hiện công việc.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_item_id | bigint unsigned | No | — | FK CASCADE |
| reporter_user_id | bigint unsigned | Yes | null | FK nullOnDelete |
| completed_at | datetime | Yes | null | INDEX |
| report_document_number | varchar(255) | Yes | null | |
| report_document_excerpt | text | Yes | null | |
| report_document_content | text | Yes | null | |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id, INDEX |
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
Nhắc việc tự động.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_item_id | bigint unsigned | No | — | FK CASCADE |
| remind_at | datetime | No | — | |
| sent_at | datetime | Yes | null | |
| channel | varchar(255) | No | — | system, email, zalo, sms |
| recipient_department_id | bigint unsigned | Yes | null | FK nullOnDelete |
| recipient_user_id | bigint unsigned | Yes | null | FK nullOnDelete |
| status | varchar(255) | No | 'pending' | pending, sent, failed |
| error_message | text | Yes | null | |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

### Sơ đồ quan hệ (Module TaskAssignment)

```
task_assignment_types ──1-n──► task_assignment_documents
                                    ├── 1-n ──► task_assignment_document_attachments ──► media
                                    └── 1-n ──► task_assignment_items
                                                    ├── n-n ──► task_assignment_item_department ◄── task_assignment_departments
                                                    ├── n-n ──► task_assignment_item_user ◄── users
                                                    ├── 1-n ──► task_assignment_item_reports
                                                    │               └── 1-n ──► task_assignment_item_report_attachments ──► media
                                                    └── 1-n ──► task_assignment_reminders

task_assignment_item_types ──1-n──► task_assignment_items
```

---

## 5. Cuộc họp nội bộ (Module Meeting)

Module đa tổ chức — tất cả bảng nghiệp vụ có `organization_id` và scope theo tenant hiện tại.

### Bảng danh mục (catalog)

Các bảng `meeting_types`, `meeting_locations`, `meeting_document_types`, `meeting_attendee_groups` có cùng cấu trúc cơ bản:

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK, auto increment |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id, nullOnDelete |
| name | varchar(255) | No | — | |
| description | text | Yes | null | |
| status | varchar(255) | No | 'active' | active, inactive |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

Riêng `meeting_locations` có thêm: `address` (varchar, nullable), `google_maps_url` (varchar, nullable).

### `meeting_minutes_templates`
Template biên bản họp — **không scope theo organization** (dùng chung toàn hệ thống).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| name | varchar(255) | No | — | |
| description | varchar(500) | Yes | null | |
| media_id | bigint unsigned | Yes | null | FK → media.id (file template) |
| is_default | boolean | No | false | |
| status | varchar(20) | No | 'active' | active, inactive |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_settings`
Cấu hình cuộc họp — singleton 1 row / 1 organization. Lưu ảnh màn chiếu, chữ ký chủ tọa, icon QR.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | UNIQUE FK → organizations.id CASCADE |
| projector_image_media_id | bigint unsigned | Yes | null | FK → media.id |
| chairperson_signature_media_id | bigint unsigned | Yes | null | FK → media.id |
| qr_icon_media_id | bigint unsigned | Yes | null | FK → media.id |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_attendees`
Danh sách đại biểu của tổ chức (catalog cố định, không gắn với 1 cuộc họp cụ thể). Mỗi user chỉ có 1 row / 1 org.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id CASCADE |
| user_id | bigint unsigned | No | — | FK → users.id CASCADE |
| position_name | varchar(255) | Yes | null | Chức vụ |
| department_name | varchar(255) | Yes | null | Phòng ban |
| status | varchar(255) | No | 'active' | active, inactive |
| note | text | Yes | null | |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(organization_id, user_id) `meeting_attendees_org_user_unique`.

### `meeting_attendee_group_members`
Pivot: đại biểu ↔ nhóm (n-n).

| Cột | Kiểu | Ràng buộc / Ghi chú |
|-----|------|---------------------|
| meeting_attendee_id | bigint unsigned | FK → meeting_attendees.id CASCADE |
| meeting_attendee_group_id | bigint unsigned | FK → meeting_attendee_groups.id CASCADE |

### `meetings`
Cuộc họp chính.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id CASCADE |
| meeting_type_id | bigint unsigned | Yes | null | FK → meeting_types.id nullOnDelete |
| meeting_location_id | bigint unsigned | Yes | null | FK → meeting_locations.id nullOnDelete |
| chairperson_meeting_attendee_id | bigint unsigned | Yes | null | FK → meeting_attendees.id nullOnDelete (chủ tọa) |
| operator_meeting_attendee_id | bigint unsigned | Yes | null | FK → meeting_attendees.id nullOnDelete (thư ký) |
| qr_manager_user_id | bigint unsigned | Yes | null | FK → users.id nullOnDelete (quản lý QR check-in) |
| title | varchar(255) | No | — | |
| is_public | boolean | No | false | |
| content | text | Yes | null | |
| start_time | datetime | No | — | |
| attendance_open_at | datetime | Yes | null | Mở cửa điểm danh |
| attendance_close_at | datetime | Yes | null | Đóng cửa điểm danh |
| end_time | datetime | Yes | null | |
| status | varchar(255) | No | 'draft' | draft, published, cancelled |
| view_count | unsigned int | No | 0 | |
| published_at | datetime | Yes | null | |
| attendance_locked | boolean | No | false | Khóa điểm danh thủ công |
| checkin_token | uuid | Yes | null | UNIQUE — FE gen QR cho check-in |
| projector_image_media_id | bigint unsigned | Yes | null | Ảnh hiển thị Tab màn chiếu |
| current_meeting_agenda_id | bigint unsigned | Yes | null | FK → meeting_agendas.id nullOnDelete (highlight điều hành) |
| current_meeting_discussion_registration_id | bigint unsigned | Yes | null | FK → meeting_discussion_registrations.id nullOnDelete |
| created_by / updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |
| deleted_at | timestamp | Yes | null | Soft delete |

INDEX: (organization_id, status), (organization_id, is_public), (start_time).

### `meeting_agendas`
Chương trình họp (có thể phân cấp cha-con).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| parent_id | bigint unsigned | Yes | null | FK → meeting_agendas.id nullOnDelete |
| content | text | No | — | |
| person_in_charge | varchar(255) | Yes | null | |
| start_time / end_time | time | Yes | null | Giờ dự kiến |
| allow_discussion_registration | boolean | No | false | |
| allow_question_registration | boolean | No | false | |
| sort_order | unsigned int | No | 0 | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_documents`
Tài liệu đính kèm cuộc họp / chương trình.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_agenda_id | bigint unsigned | Yes | null | FK → meeting_agendas.id nullOnDelete |
| meeting_document_type_id | bigint unsigned | Yes | null | FK → meeting_document_types.id nullOnDelete |
| title | varchar(255) | No | — | |
| document_number | varchar(255) | Yes | null | |
| summary | text | Yes | null | |
| media_id | bigint unsigned | Yes | null | FK → media.id nullOnDelete (file chính) |
| is_public | boolean | No | false | |
| download_count | unsigned int | No | 0 | |
| sort_order | unsigned int | No | 0 | |
| created_by / updated_by | bigint unsigned | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_participants`
Đại biểu được mời vào 1 cuộc họp cụ thể (tạo khi publish meeting từ danh sách `meeting_attendees`).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_attendee_id | bigint unsigned | No | — | FK → meeting_attendees.id CASCADE |
| display_name | varchar(255) | No | — | Snapshot tên lúc invite |
| position_name | varchar(255) | Yes | null | |
| department_name | varchar(255) | Yes | null | |
| email | varchar(255) | Yes | null | |
| phone | varchar(255) | Yes | null | |
| response_status | varchar(255) | No | 'pending' | pending, accepted, declined |
| absence_reason | text | Yes | null | |
| responded_at | datetime | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(meeting_id, meeting_attendee_id).

### `meeting_guests`
Khách mời external per-meeting (không có user account).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| name | varchar(255) | No | — | Họ tên |
| position_name | varchar(255) | Yes | null | Chức vụ |
| phone | varchar(30) | No | — | |
| email | varchar(255) | No | — | |
| organization_name | varchar(255) | Yes | null | Đơn vị (text tự nhập) |
| invited_at | datetime | Yes | null | Lần gần nhất gửi thư mời thành công |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_attendances`
Điểm danh tham dự.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_participant_id | bigint unsigned | No | — | FK → meeting_participants.id CASCADE |
| status | varchar(255) | No | 'pending' | pending, present, absent |
| checkin_method | varchar(255) | Yes | null | qr, manual |
| checked_in_at | datetime | Yes | null | |
| checked_in_by | bigint unsigned | Yes | null | FK → users.id |
| note | text | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(meeting_id, meeting_participant_id).

### `meeting_vote_topics`
Chủ đề biểu quyết. Phase derive từ `opened_at` + `closed_at` (không có cột `status`).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_agenda_id | bigint unsigned | Yes | null | FK → meeting_agendas.id nullOnDelete |
| title | varchar(255) | No | — | |
| vote_type | varchar(255) | No | 'agree_disagree_abstain' | Kiểu phiếu |
| ballot_mode | varchar(255) | No | 'anonymous' | anonymous, named |
| show_result_on_projector | boolean | No | false | |
| show_result_on_personal_device | boolean | No | false | |
| sort_order | unsigned int | No | 0 | |
| opened_at | datetime | Yes | null | null = chưa mở |
| closed_at | datetime | Yes | null | null = chưa đóng |
| created_by / updated_by | bigint unsigned | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_vote_responses`
Phiếu biểu quyết của từng đại biểu.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_vote_topic_id | bigint unsigned | No | — | FK → meeting_vote_topics.id CASCADE |
| meeting_participant_id | bigint unsigned | No | — | FK → meeting_participants.id CASCADE |
| option | varchar(255) | No | — | agree, disagree, abstain |
| voted_at | datetime | No | — | |
| created_at / updated_at | timestamp | Yes | null | |

Ràng buộc: UNIQUE(meeting_vote_topic_id, meeting_participant_id).

### `meeting_discussion_registrations`
Đăng ký phát biểu / chất vấn.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_agenda_id | bigint unsigned | Yes | null | FK → meeting_agendas.id nullOnDelete |
| meeting_participant_id | bigint unsigned | No | — | FK → meeting_participants.id CASCADE |
| type | varchar(255) | No | 'discussion' | discussion, question |
| content | text | No | — | Nội dung đăng ký |
| media_id | bigint unsigned | Yes | null | FK → media.id (legacy — xem attachments) |
| status | varchar(255) | No | 'registered' | registered, speaking, done, skipped |
| completed_at | datetime | Yes | null | |
| sort_order | unsigned int | No | 0 | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_discussion_registration_attachments`
Đính kèm cho đăng ký phát biểu (multi-file, thay thế `media_id` đơn).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK → organizations.id CASCADE |
| meeting_discussion_registration_id | bigint unsigned | No | — | FK → meeting_discussion_registrations.id CASCADE |
| media_id | bigint unsigned | No | — | FK → media.id CASCADE |
| file_name | varchar(255) | Yes | null | Tên hiển thị |
| sort_order | unsigned int | No | 0 | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_personal_notes`
Ghi chú cá nhân của đại biểu trong phiên họp.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_participant_id | bigint unsigned | No | — | FK → meeting_participants.id CASCADE |
| content | longtext | No | — | |
| sort_order | unsigned int | No | 0 | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_personal_note_attachments`
Đính kèm ghi chú cá nhân.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_personal_note_id | bigint unsigned | No | — | FK → meeting_personal_notes.id CASCADE |
| media_id | bigint unsigned | No | — | FK → media.id CASCADE |
| sort_order | unsigned int | No | 0 | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_invitations`
Giấy mời gửi cho từng đại biểu/khách mời khi publish meeting.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_participant_id | bigint unsigned | Yes | null | FK → meeting_participants.id CASCADE (1 trong 2 phải có) |
| meeting_attendee_id | bigint unsigned | Yes | null | FK → meeting_attendees.id CASCADE (chủ tọa/thư ký trực tiếp) |
| send_type | varchar(255) | No | 'now' | now, scheduled |
| scheduled_at | datetime | Yes | null | |
| sent_at | datetime | Yes | null | |
| status | varchar(255) | No | 'pending' | pending, sent, failed |
| error_message | text | Yes | null | |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_reminders`
Nhắc lịch họp (manual hoặc scheduled).

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| organization_id | bigint unsigned | No | — | FK CASCADE |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| reminder_type | varchar(255) | No | 'manual' | manual, scheduled |
| scheduled_at | datetime | Yes | null | |
| sent_at | datetime | Yes | null | |
| message | text | Yes | null | |
| status | varchar(255) | No | 'pending' | pending, sent, failed |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| created_at / updated_at | timestamp | Yes | null | |

### `meeting_views`
Log lượt xem cuộc họp / tài liệu.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| meeting_id | bigint unsigned | No | — | FK → meetings.id CASCADE |
| meeting_document_id | bigint unsigned | Yes | null | FK → meeting_documents.id CASCADE (null = xem meeting) |
| user_id | bigint unsigned | Yes | null | FK → users.id nullOnDelete |
| ip_address | varchar(45) | Yes | null | |
| user_agent | text | Yes | null | |
| viewed_at | datetime | No | — | |

### Sơ đồ quan hệ (Module Meeting)

```
meeting_types ──────────────────────────────────────────────┐
meeting_locations ───────────────────────────────────────────┤
meeting_attendee_groups ──n-n (pivot)──► meeting_attendees ──┤
                                                             ▼
                                                         meetings ──1-n──► meeting_agendas (cây)
                                                             │                    └── 1-n ──► meeting_documents ──► media
                                                             │                    └── 1-n ──► meeting_vote_topics
                                                             │                                    └── 1-n ──► meeting_vote_responses ◄── meeting_participants
                                                             │                    └── 1-n ──► meeting_discussion_registrations
                                                             │                                    └── 1-n ──► meeting_discussion_registration_attachments ──► media
                                                             │
                                                             ├── 1-n ──► meeting_participants ──► meeting_attendees
                                                             │               ├── 1-n ──► meeting_attendances
                                                             │               └── 1-n ──► meeting_personal_notes ──► meeting_personal_note_attachments ──► media
                                                             │
                                                             ├── 1-n ──► meeting_guests
                                                             ├── 1-n ──► meeting_invitations
                                                             ├── 1-n ──► meeting_reminders
                                                             └── 1-n ──► meeting_views
```

---

*File được cập nhật theo migration trong `database/migrations/`.*
