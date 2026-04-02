# Tổng hợp module quản lý giao việc liên phòng ban (TaskAssignment)

**Ngày tổng hợp:** 2026-04-02

---

## 1. Bối cảnh nghiệp vụ thực tế

Hiện tại việc theo dõi công việc các phòng đang quản lý qua Google Sheets:

- **Cuối tháng**: Các phòng nhập chương trình công tác tháng tiếp theo (công việc, người phụ trách, thời gian, kết quả). Theo 2 đầu việc: Thường trực Thành ủy giao, Công việc chuyên môn.
- **Trong tháng**: Các phòng vào Google Sheets báo cáo tiến độ, cập nhật công việc phát sinh. Theo 3 đầu việc: Thường trực Thành ủy giao, Công việc chuyên môn, Công việc phát sinh.
- **Trạng thái theo dõi**: Đã hoàn thành, Chưa hoàn thành, Tiếp tục thực hiện, Theo dõi thường xuyên, Hủy/Không thực hiện.
- **Cảnh báo thời hạn**: Dưới 2 ngày (đỏ), dưới 4 ngày (vàng), từ 5 ngày trở lên (xanh).
- **Báo cáo**: Các phòng nêu số văn bản + ngày phát hành để văn phòng đối chiếu.
- **Đầu tháng**: Văn phòng tổng hợp tất cả công việc của Ban để báo cáo tại cuộc họp giao ban (bảng tổng hợp đầu việc + công việc cụ thể từng phòng + chương trình tháng tiếp theo).

**Vấn đề cần giải quyết**: Thay thế Google Sheets bằng hệ thống phần mềm quản lý tập trung, tự động nhắc việc, thống kê báo cáo, phân quyền theo phòng ban.

---

## 2. Giải pháp đã triển khai

Module `TaskAssignment` trong hệ thống `quandh-core` (Laravel 12), hoạt động **độc lập không theo organization_id**.

---

## 3. Cấu trúc dữ liệu (11 bảng)

### 3.1 Danh mục

| Bảng | Mô tả | Trường chính |
|------|--------|-------------|
| `task_assignment_departments` | Phòng ban nội bộ | code (unique), name, description, status, sort_order |
| `task_assignment_types` | Loại văn bản giao việc (VD: Thường trực Thành ủy giao, Công việc chuyên môn, Công việc phát sinh) | name, description, status |
| `task_assignment_item_types` | Loại công việc | name, description, status |

### 3.2 Nghiệp vụ chính

| Bảng | Mô tả | Trường chính |
|------|--------|-------------|
| `task_assignment_documents` | Văn bản giao việc (chương trình công tác) | name, summary, issue_date, type_id, status (draft/issued), issued_at |
| `task_assignment_document_attachments` | Tệp đính kèm văn bản | document_id, media_id, file_name, sort_order |
| `task_assignment_items` | Công việc thuộc văn bản | document_id, name, description, item_type_id, deadline_type, start_at, end_at, processing_status, completion_percent, priority, completed_at |
| `task_assignment_item_department` | Pivot: công việc ↔ phòng ban | item_id, department_id, role (main/cooperate) |
| `task_assignment_item_user` | Pivot: công việc ↔ người dùng | item_id, department_id, user_id, assignment_role, assignment_status, assigned_at, accepted_at, completed_at, note |
| `task_assignment_item_reports` | Báo cáo kết quả thực hiện | item_id, reporter_user_id, completed_at, report_document_number, report_document_excerpt, report_document_content |
| `task_assignment_item_report_attachments` | Tệp đính kèm báo cáo | report_id, media_id, file_name, sort_order |
| `task_assignment_reminders` | Nhắc việc tự động (Phase 3) | item_id, remind_at, sent_at, channel, recipient, status |

### 3.3 Sơ đồ quan hệ

```
task_assignment_types ──1-n──► task_assignment_documents
                                    ├── 1-n ──► attachments ──► media
                                    └── 1-n ──► task_assignment_items
                                                    ├── n-n ──► departments
                                                    ├── n-n ──► users
                                                    ├── 1-n ──► reports ──► report_attachments ──► media
                                                    └── 1-n ──► reminders

task_assignment_item_types ──1-n──► task_assignment_items
```

---

## 4. Mapping nghiệp vụ ↔ hệ thống

| Nghiệp vụ thực tế | Hệ thống |
|---|---|
| Chương trình công tác tháng | `task_assignment_documents` (status: draft → issued) |
| Loại đầu việc (TT Thành ủy giao, Chuyên môn, Phát sinh) | `task_assignment_types` |
| Phòng ban (VP, Phòng Tuyên truyền, ...) | `task_assignment_departments` |
| Từng công việc cụ thể | `task_assignment_items` |
| Phòng chủ trì / phối hợp | `task_assignment_item_department` (role: main/cooperate) |
| Người phụ trách / hỗ trợ | `task_assignment_item_user` (assignment_role: main/support) |
| Đã hoàn thành | `processing_status = done`, `completion_percent = 100` |
| Chưa hoàn thành | `processing_status = todo` hoặc `in_progress` |
| Tiếp tục thực hiện | `processing_status = in_progress` |
| Theo dõi thường xuyên | `deadline_type = no_deadline` |
| Hủy/Không thực hiện | `processing_status = cancelled` |
| Cảnh báo màu (đỏ/vàng/xanh) | `task_assignment_reminders` + logic so sánh `end_at` với ngày hiện tại |
| Báo cáo bằng số văn bản + ngày | `task_assignment_item_reports` (report_document_number, completed_at) |
| Bảng tổng hợp họp giao ban | API stats-by-department, stats-by-time, export |

---

## 5. Trạng thái và luồng xử lý

### 5.1 Văn bản giao việc
```
draft ──────► issued
  ↑              │
  └──────────────┘ (có thể mở lại)
```
- `draft`: Cho phép chỉnh sửa, thêm/sửa/xóa công việc, đính kèm tệp.
- `issued`: Khóa chỉnh sửa cốt lõi. Validate: phải có ít nhất 1 công việc, công việc có thời hạn phải có `end_at`.

### 5.2 Công việc
```
todo ──► in_progress ──► done
  │          │              │
  ├──► paused ◄─────────────┤
  ├──► cancelled             │
  └──► overdue (tự động)     │
         └──────────────────►┘
```

**Business rules tự động**:
- `processing_status = done` → `completion_percent = 100`, set `completed_at`
- `completion_percent = 100` → auto `done`, set `completed_at`
- Mở lại từ `done` → clear `completed_at`
- Quá `end_at` mà chưa done → `overdue`

### 5.3 Nhận việc (user assignment)
```
assigned ──► accepted ──► done
               │
               └──► rejected
```

---

## 6. API endpoints (tổng hợp)

### 6.1 Danh mục (3 resources × 13 endpoints = 39 endpoints)

Mỗi resource (departments, types, item-types) có:
- `GET /public` + `GET /public-options` (không cần auth)
- `GET /stats`, `GET /`, `GET /{id}`, `POST /`, `PUT /{id}`, `DELETE /{id}`
- `POST /bulk-delete`, `PATCH /bulk-status`, `PATCH /{id}/status`
- `GET /export`, `POST /import`

### 6.2 Văn bản giao việc (11 endpoints)

| Method | Path | Mô tả |
|--------|------|-------|
| GET | /task-assignment-documents/stats | Thống kê draft/issued |
| GET | /task-assignment-documents | Danh sách phân trang |
| GET | /task-assignment-documents/{id} | Chi tiết + items + attachments |
| POST | /task-assignment-documents | Tạo mới (kèm files) |
| PUT | /task-assignment-documents/{id} | Cập nhật (kèm files, remove_attachment_ids) |
| DELETE | /task-assignment-documents/{id} | Xóa |
| POST | /task-assignment-documents/bulk-delete | Xóa hàng loạt |
| PATCH | /task-assignment-documents/bulk-status | Cập nhật trạng thái hàng loạt |
| PATCH | /task-assignment-documents/{id}/status | Chuyển trạng thái (draft ↔ issued) |
| GET | /task-assignment-documents/export | Xuất Excel |
| POST | /task-assignment-documents/import | Nhập Excel |

### 6.3 Công việc (13 endpoints)

| Method | Path | Mô tả |
|--------|------|-------|
| GET | /task-assignment-items/stats | Thống kê theo trạng thái |
| GET | /task-assignment-items | Danh sách (filter: search, status, priority, department_id, user_id, date ranges) |
| GET | /task-assignment-items/{id} | Chi tiết + departments + users + reports |
| POST | /task-assignment-items | Tạo mới (kèm departments[], users[]) |
| PUT | /task-assignment-items/{id} | Cập nhật |
| DELETE | /task-assignment-items/{id} | Xóa |
| POST | /task-assignment-items/bulk-delete | Xóa hàng loạt |
| PATCH | /task-assignment-items/bulk-status | Cập nhật trạng thái hàng loạt |
| PATCH | /task-assignment-items/{id}/status | Đổi trạng thái |
| PATCH | /task-assignment-items/{id}/progress | Cập nhật tiến độ (auto sync done ↔ 100%) |
| GET | /task-assignment-items/export | Xuất Excel |
| POST | /task-assignment-items/import | Nhập Excel |

### 6.4 Báo cáo công việc (5 endpoints)

| Method | Path | Mô tả |
|--------|------|-------|
| GET | /task-assignment-item-reports?task_assignment_item_id=X | Danh sách báo cáo theo công việc |
| GET | /task-assignment-item-reports/{id} | Chi tiết báo cáo |
| POST | /task-assignment-item-reports | Tạo báo cáo (kèm files) |
| PUT | /task-assignment-item-reports/{id} | Cập nhật báo cáo |
| DELETE | /task-assignment-item-reports/{id} | Xóa báo cáo |

**Tổng: ~68 endpoints**

---

## 7. Cấu trúc code (94 files)

```
app/Modules/TaskAssignment/
├── Controllers/     (6)   – Điều phối request → service → response
├── Enums/           (9)   – Trạng thái, loại, mức độ ưu tiên
├── Models/          (11)  – Eloquent + relationships + scopeFilter
├── Requests/        (26)  – Validation (extend BaseRequest)
├── Resources/       (10)  – Transform API response (5 Resource + 5 Collection)
├── Services/        (5)   – Business logic + transaction
├── Exports/         (4)   – Xuất Excel (Maatwebsite)
├── Imports/         (4)   – Nhập Excel (Maatwebsite)
└── Routes/          (6)   – Route definitions + permission middleware

database/
├── migrations/      (1)   – 11 bảng trong 1 migration
└── factories/       (6)   – Factory cho Scribe

docs/api/            (6)   – API documentation markdown
```

---

## 8. Phân quyền (Permission)

6 resource groups trong `PermissionSeeder`:

| Resource | Actions |
|----------|---------|
| `task-assignment-departments` | stats, index, show, store, update, destroy, bulkDestroy, bulkUpdateStatus, changeStatus, export, import |
| `task-assignment-types` | (giống trên) |
| `task-assignment-item-types` | (giống trên) |
| `task-assignment-documents` | stats, index, show, store, update, destroy, bulkDestroy, bulkUpdateStatus, changeStatus, export, import |
| `task-assignment-items` | stats, index, show, store, update, destroy, bulkDestroy, bulkUpdateStatus, changeStatus, export, import, updateProgress |
| `task-assignment-item-reports` | index, show, store, update, destroy |

---

## 9. Lộ trình triển khai

| Phase | Nội dung | Trạng thái |
|-------|----------|-----------|
| Phase 1 | Core CRUD + dữ liệu chuẩn (migration, models, CRUD, export/import, permissions) | Đã triển khai |
| Phase 2 | Theo dõi tiến độ & thống kê nâng cao (stats-by-department, stats-by-user, stats-by-time, overdue, upcoming-deadline) | Chưa triển khai |
| Phase 3 | Nhắc việc tự động (scheduler + queue job + bảng reminder đã sẵn) | Chưa triển khai |
| Phase 4 | Tối ưu vận hành (cache thống kê, phân quyền sâu theo phòng ban, audit log) | Chưa triển khai |

---

## 10. Ghi chú kỹ thuật

- **Không dùng `organization_id`**: Module vận hành độc lập, phòng ban quản lý riêng qua `task_assignment_departments`.
- **File đính kèm**: Dùng bảng attachment riêng (`*_attachments`) với FK `media_id` → bảng `media` (Spatie MediaLibrary). Upload qua `MediaService`.
- **Middleware**: `auth:sanctum` + `set.permissions.team` + `log.activity` (không dùng `ensure.route.org`).
- **DateTime format**: `H:i:s d/m/Y` (theo chuẩn dự án).

*Tài liệu tổng hợp module TaskAssignment - Phase 1.*
