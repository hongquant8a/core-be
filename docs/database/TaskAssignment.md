# DATABASE DESIGN — Module TaskAssignment

> Ngày tạo: 00:00:00 16/06/2026  
> Cập nhật lần cuối: 09:05:00 11/09/2026

Giao việc liên phòng ban.

**Lưu ý:** Module đa tổ chức — model dùng `HasOrganizationScope`, mọi query tự động scope theo `organization_id` hiện tại từ middleware `set.permissions.team`. Phòng ban được quản lý riêng qua bảng `task_assignment_departments`.

---

### `task_assignment_departments`
Phòng ban nội bộ phục vụ nghiệp vụ giao việc.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
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
| name | text | No | — | Đổi từ varchar(255) ngày 10/09/2026: tiêu đề văn bản hành chính gói cả số hiệu, ngày và cơ quan ban hành nên thường vượt 255 |
| summary | text | Yes | null | |
| ai_source_content | longtext | Yes | null | Thêm 11/09/2026: nguyên văn dán vào ô phân tích AI. LONGTEXT vì trần 50.000 ký tự của AnalyzeDocumentRequest có thể chạm 150KB khi tiếng Việt tốn 3 byte/ký tự. Không select ở màn danh sách (xem `TaskAssignmentDocument::LIST_COLUMNS`) |
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
| name | text | No | — | Đổi từ varchar(255) ngày 19/08/2026: tên công việc do AI trích từ văn bản chỉ đạo thường là cả một câu, dài nhất trong dữ liệu thật là 1.960 ký tự |
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
| assignee_user_id | bigint unsigned | Yes | null | FK → users.id nullOnDelete (người được giao việc tại thời điểm báo cáo) |
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

### Xoá mềm — bốn bảng

| Bảng | Xoá mềm từ | Thùng rác |
|------|-----------|-----------|
| `task_assignment_petitions` | 11/09/2026 | có, kèm khôi phục |
| `task_assignment_documents` | 12/09/2026 | có, kèm khôi phục |
| `task_assignment_items` | 12/09/2026 | có, kèm khôi phục |
| `task_assignment_item_reports` | 12/09/2026 | có, kèm khôi phục |

Các bảng còn lại của module vẫn xoá cứng.

**Khoá ngoại cascade chỉ kích hoạt khi xoá CỨNG.** Xoá mềm một văn bản không tự kéo theo công việc bên trong, nên service làm tường minh: xoá văn bản → xoá mềm công việc → xoá mềm báo cáo, cả ba tầng dùng **chung một mốc `deleted_at`**.

Mốc chung là thứ cho phép khôi phục đúng bộ: khôi phục văn bản chỉ lấy lại những công việc và báo cáo bị xoá **cùng lần đó**. Công việc bị xoá lẻ trước đó (mốc khác) vẫn ở nguyên trong thùng rác của nó.

Ghi từng dòng bằng Eloquent chứ không mass update, để `TaskAssignmentItemObserver` chạy:
- `deleted` → huỷ lịch nhắc đang chờ (không thì đến hạn vẫn nhắc về việc đã xoá).
- `restored` → dựng lại lịch nhắc theo thời hạn hiện có.

**Truy vấn `DB::table()` không có global scope của SoftDeletes.** Mọi thống kê thô đã được thêm `whereNull('deleted_at')` — thiếu là đếm cả bản ghi trong thùng rác. Cùng lý do, ba chỗ chặn xoá (phòng ban, nhân viên, người dùng) cũng phải loại công việc đã xoá, không thì bị chặn vì việc đã nằm trong thùng rác.

**Tệp đính kèm của báo cáo được giữ nguyên khi xoá mềm** — trước đây `destroy` xoá cứng cả attachment lẫn file trên đĩa, nếu giữ nguyên thì khôi phục ra báo cáo rỗng và file mất vĩnh viễn.

### `task_assignment_item_extensions`
Yêu cầu gia hạn thời hạn công việc — người thực hiện xin, người giao việc duyệt.

| Cột | Kiểu | Nullable | Mặc định | Ràng buộc / Ghi chú |
|-----|------|----------|----------|---------------------|
| id | bigint unsigned | No | — | PK |
| task_assignment_item_id | bigint unsigned | No | — | FK CASCADE (`fk_ta_extensions_item`) |
| requested_by_user_id | bigint unsigned | Yes | null | FK SET NULL — người xin |
| current_end_at | datetime | Yes | null | **Hạn tại thời điểm xin** — chụp lại để dòng lịch sử không mất nghĩa sau khi `items.end_at` đổi |
| requested_end_at | datetime | No | — | Hạn xin dời tới. **Không ràng buộc** phải sau hạn cũ hay sau hiện tại |
| reason | text | No | — | Lý do xin — bắt buộc, tối thiểu 10 ký tự |
| status | varchar(20) | No | pending | `pending` · `approved` · `rejected` · `cancelled` |
| reviewed_by_user_id | bigint unsigned | Yes | null | FK SET NULL — người duyệt |
| reviewed_at | datetime | Yes | null | |
| review_note | text | Yes | null | Ghi chú khi duyệt / lý do khi từ chối (bắt buộc với từ chối) |
| organization_id | bigint unsigned | Yes | null | FK SET NULL |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |

Index: `(task_assignment_item_id, status)`, `(status, created_at)`

Tên khoá ngoại đặt thủ công (`fk_ta_extensions_*`): tên Laravel tự sinh cho bảng này vượt giới hạn 64 ký tự của MySQL.

**Quy tắc nghiệp vụ:**

- Mỗi công việc chỉ có tối đa **1 yêu cầu `pending`**. MySQL không có unique index có điều kiện nên kiểm ở service kèm `lockForUpdate()`.
- Chỉ xin được khi `deadline_type = has_deadline` và `processing_status` không thuộc `done` / `cancelled` / `pending_approval`.
- **`items.end_at` chỉ đổi khi yêu cầu được DUYỆT.** Trong lúc chờ duyệt, công việc quá hạn vẫn tính là quá hạn và lịch nhắc vẫn chạy theo hạn cũ — gửi yêu cầu không phải là cách tạm hoãn.
- Khi duyệt, `items.end_at` phải được ghi bằng **Eloquent** để `TaskAssignmentItemObserver` chạy và `ReminderScheduler` dựng lại lịch nhắc theo hạn mới.
- Morph alias: `task_assignment_item_extension` (dùng cho bảng `notifications`).

> Migration: `2026_09_12_000000_create_task_assignment_item_extensions_table.php`

### ~~`task_assignment_reminders`~~ — **ĐÃ XÓA ngày 28/06/2026**

> Migration drop: `2026_06_28_000003_drop_old_reminder_tables.php`

Bảng này đã bị drop và thay thế bởi bảng `reminders` (polymorphic) trong Core.  
`remindable_type = 'App\Modules\TaskAssignment\Models\TaskAssignmentItem'`

Xem schema chi tiết tại [docs/database/Core.md](Core.md) — Mục 9.6 `reminders`.

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
| processing_status | varchar(30) | No | 'new' | new, processing, completed, paused, cancelled. Đơn `completed` bị KHOÁ: không sửa / không đổi trạng thái / không xoá, phải `unlock` (quyền `task-assignment-petitions.manage`) trước |
| completed_at | datetime | Yes | null | |
| document_number | varchar(255) | Yes | null | Số hiệu văn bản trả lời |
| document_excerpt | text | Yes | null | Trích yếu |
| response_content | text | Yes | null | Nội dung trả lời |
| organization_id | bigint unsigned | Yes | null | FK → organizations.id nullOnDelete |
| created_by | bigint unsigned | Yes | null | FK → users.id |
| updated_by | bigint unsigned | Yes | null | FK → users.id |
| created_at | timestamp | Yes | null | |
| updated_at | timestamp | Yes | null | |
| deleted_at | timestamp | Yes | null | **Xoá mềm** từ 11/09/2026 — hồ sơ kiến nghị của người dân không biến mất vì một cú bấm nhầm; truy vấn thường tự loại, tra lại bằng `withTrashed()` |

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
task_assignment_types ──1-n──► task_assignment_documents
                                    ├── 1-n ──► task_assignment_document_attachments ──► media
                                    └── 1-n ──► task_assignment_items
                                                    ├── n-n ──► task_assignment_item_user ◄── users, task_assignment_departments
                                                    ├── 1-n ──► task_assignment_item_attachments ──► media
                                                    ├── 1-n ──► task_assignment_item_reports
                                                    │               └── 1-n ──► task_assignment_item_report_attachments ──► media
                                                    └── 1-n ──► reminders [Core] (remindable_type = TaskAssignmentItem)

task_assignment_item_types ──1-n──► task_assignment_items
task_assignment_items ──1-n──► task_assignment_item_extensions ──► users (người xin, người duyệt)

task_assignment_departments
    ├── 1-n ──► task_assignment_employees ◄── users
    ├── 1-n ──► task_assignment_petitions ──► task_assignment_petition_attachments ──► media
    └── n-n ──► task_assignment_item_user
```

---

*File được cập nhật theo migration trong `database/migrations/`.*
