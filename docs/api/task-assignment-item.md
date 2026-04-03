# API Công việc (Task Assignment Item)

Quản lý công việc trong hệ thống giao việc liên phòng ban: thống kê, danh sách (với bộ lọc nâng cao), chi tiết, CRUD, xóa/cập nhật trạng thái hàng loạt, đổi trạng thái, cập nhật tiến độ, xuất/nhập Excel. Mỗi công việc có thể giao cho nhiều phòng ban và nhiều người dùng (quan hệ nhiều-nhiều).

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** tất cả endpoint chỉ thao tác dữ liệu thuộc tổ chức hiện tại (`organization_id` theo `X-Organization-Id`).

**Base path:** `/api/task-assignment-items`

**Enum values:**
- `processing_status`: `todo` | `in_progress` | `done` | `overdue` | `paused` | `cancelled`
- `deadline_type`: `has_deadline` | `no_deadline`
- `priority`: `low` | `medium` | `high` | `urgent`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats` |
| **Auth** | Bắt buộc. |
| **Query** | `search`, `processing_status`, `priority`, `deadline_type`, `department_id`, `user_id`, `task_assignment_document_id`, `start_from` / `start_to`, `end_from` / `end_to`, `from_date` / `to_date` (lọc theo `created_at`), `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | `{ "total": 100, "todo": 20, "in_progress": 30, "done": 25, "overdue": 10, "paused": 10, "cancelled": 5 }` — total (sau lọc), các key còn lại là số lượng theo từng trạng thái. |

---

## Danh sách công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items` |
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên công việc), `processing_status` (todo \| in_progress \| done \| overdue \| paused \| cancelled), `priority` (low \| medium \| high \| urgent), `deadline_type` (has_deadline \| no_deadline), `department_id` (ID phòng ban được giao), `user_id` (ID người dùng được giao), `assignment_role` (main \| cooperate — vai trò phòng ban), `assignment_status` (lọc theo trạng thái phân công người dùng), `task_assignment_document_id` (ID văn bản giao việc), `task_assignment_item_type_id`, `start_from` / `start_to` (YYYY-MM-DD — lọc theo `start_at`), `end_from` / `end_to` (YYYY-MM-DD — lọc theo `end_at`), `from_date` / `to_date` (YYYY-MM-DD — lọc theo `created_at`), `sort_by` (id \| name \| start_at \| end_at \| priority \| completion_percent \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection; mỗi item kèm `departments`, `users`, tên văn bản và loại công việc. |

---

## Chi tiết công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/{id}` |
| **Auth** | Bắt buộc. |
| **UrlParam** | `id` — ID công việc. |
| **Response** | Object công việc (TaskAssignmentItemResource), kèm `departments`, `users`, `task_assignment_document`, `task_assignment_item_type`. |

---

## Tạo công việc

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-items` |
| **Auth** | Bắt buộc. |
| **Body** | `name` (required), `description` (optional), `task_assignment_document_id` (required, ID văn bản), `task_assignment_item_type_id` (optional, ID loại công việc), `deadline_type` (required: has_deadline \| no_deadline), `start_at` (optional, YYYY-MM-DD HH:mm:ss), `end_at` (optional, YYYY-MM-DD HH:mm:ss — bắt buộc nếu `deadline_type = has_deadline`), `processing_status` (required: todo \| in_progress \| done \| overdue \| paused \| cancelled), `completion_percent` (optional, 0-100), `priority` (required: low \| medium \| high \| urgent), `departments` (optional, mảng object `{ "department_id": 1, "role": "main" \| "cooperate" }`), `users` (optional, mảng object `{ "user_id": 2, "department_id": 1, "assignment_role": "main" \| "support" }`). |
| **Response** | 201, object công việc (kèm departments, users) + `"message": "Công việc đã được tạo thành công!"`. |

---

## Cập nhật công việc

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-items/{id}` |
| **Auth** | Bắt buộc. |
| **Body** | Giống tạo (các trường tùy chọn). `departments` và `users` sẽ ghi đè danh sách hiện tại (sync). |
| **Response** | Object công việc đã cập nhật (kèm departments, users). |

---

## Xóa công việc

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-items/{id}` |
| **Auth** | Bắt buộc. |
| **Response** | `{ "message": "Công việc đã được xóa thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-items/bulk-delete` |
| **Auth** | Bắt buộc. |
| **Body** | `ids` (array) — danh sách ID công việc. |
| **Response** | `{ "message": "Đã xóa thành công các công việc được chọn!" }`. |

---

## Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/bulk-status` |
| **Auth** | Bắt buộc. |
| **Body** | `ids` (array), `processing_status` (required: todo \| in_progress \| done \| overdue \| paused \| cancelled). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công các công việc được chọn!" }`. |

---

## Đổi trạng thái công việc

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/status` |
| **Auth** | Bắt buộc. |
| **Body** | `processing_status` (required: todo \| in_progress \| done \| overdue \| paused \| cancelled). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công!", "data": TaskAssignmentItemResource }`. |

---

## Cập nhật tiến độ

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/progress` |
| **Auth** | Bắt buộc. |
| **Body** | `processing_status` (optional: todo \| in_progress \| done \| overdue \| paused \| cancelled), `completion_percent` (optional, 0-100). Ít nhất một trong hai trường phải có giá trị. |
| **Response** | `{ "message": "Cập nhật tiến độ thành công!", "data": TaskAssignmentItemResource }`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/export` |
| **Auth** | Bắt buộc. |
| **Query** | Cùng bộ lọc với index: `search`, `processing_status`, `priority`, `deadline_type`, `department_id`, `user_id`, `task_assignment_document_id`, `start_from`, `start_to`, `end_from`, `end_to`, `from_date`, `to_date`, `sort_by`, `sort_order`. |
| **Response** | File `task-assignment-items.xlsx`. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-items/import` |
| **Auth** | Bắt buộc. |
| **Body** | `file` (required) — xlsx, xls, csv. Cột theo chuẩn export. |
| **Response** | `{ "message": "Import công việc thành công." }`. |

---

## Tải mẫu import

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/import-template` |
| **Auth** | Bắt buộc (permission: import). |
| **Response** | File `import-items-template.xlsx` — chỉ có header row: `name`, `description`, `deadline_type`, `start_at`, `end_at`, `processing_status`, `completion_percent`, `priority`. |

---

## Thống kê theo phòng ban

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-department` |
| **Auth** | Bắt buộc. |
| **Query** | `department_id`, `processing_status`, `priority`, `deadline_type`, `task_assignment_item_type_id`, `from_date`, `to_date`. |
| **Response** | Mảng `[{ "department_id": 1, "department_name": "Phòng Kỹ thuật", "department_code": "KT", "total": 10, "todo": 2, "in_progress": 4, "done": 3, "overdue": 1, "paused": 0, "cancelled": 0 }]`. |

---

## Thống kê theo người dùng

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-user` |
| **Auth** | Bắt buộc. |
| **Query** | `department_id`, `processing_status`, `priority`, `from_date`, `to_date`. |
| **Response** | Mảng `[{ "user_id": 2, "user_name": "Nguyễn Văn A", "total": 8, "todo": 1, "in_progress": 3, "done": 3, "overdue": 1, "on_time_count": 2, "overdue_done_count": 1 }]`. |

---

## Thống kê theo thời gian

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-time` |
| **Auth** | Bắt buộc. |
| **Query** | `from_date` (required, YYYY-MM-DD), `to_date` (required, YYYY-MM-DD — tối đa cách `from_date` 12 tháng), `department_id`, `user_id`, `processing_status`. |
| **Response** | Mảng `[{ "month": "2026-01", "total": 15, "done": 10, "overdue": 2, "new_tasks": 5 }]`. |

---

## Danh sách quá hạn

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/overdue` |
| **Auth** | Bắt buộc. |
| **Query** | `department_id`, `user_id`, `priority`, `sort_by`, `sort_order`, `limit`. |
| **Response** | Paginated ItemCollection — danh sách công việc có `processing_status = overdue`. |

---

## Danh sách sắp đến hạn

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/upcoming-deadline` |
| **Auth** | Bắt buộc. |
| **Query** | `days` (1-30, mặc định 3 — số ngày sắp đến hạn), `department_id`, `user_id`, `priority`, `sort_by`, `sort_order`, `limit`. |
| **Response** | Paginated ItemCollection — danh sách công việc có `end_at` trong vòng `days` ngày tới. |

---

## Business Logic

**Đồng bộ tiến độ và trạng thái (done ↔ 100%):**
- Khi `processing_status = done` → hệ thống tự động set `completion_percent = 100` và ghi nhận `completed_at`.
- Khi `completion_percent = 100` → hệ thống tự động chuyển `processing_status = done` và ghi nhận `completed_at`.
- Khi mở lại công việc từ trạng thái `done` (chuyển sang trạng thái khác) → `completed_at` được xóa (clear).

---

## Response mẫu (TaskAssignmentItemResource)

```json
{
  "id": 1,
  "name": "Báo cáo tình hình nhân sự Q1/2026",
  "description": "Tổng hợp và báo cáo tình hình nhân sự của phòng ban trong quý 1",
  "task_assignment_document_id": 1,
  "task_assignment_document": { "id": 1, "name": "Quyết định số 01/QĐ-HĐQT" },
  "task_assignment_item_type_id": 1,
  "task_assignment_item_type": { "id": 1, "name": "Nhiệm vụ thường xuyên" },
  "deadline_type": "has_deadline",
  "start_at": "2026-01-10 08:00:00",
  "end_at": "2026-03-31 17:00:00",
  "processing_status": "in_progress",
  "completion_percent": 45,
  "priority": "high",
  "completed_at": null,
  "departments": [
    { "id": 1, "name": "Phòng Nhân sự", "code": "NS", "role": "main" }
  ],
  "users": [
    { "id": 2, "name": "Nguyễn Văn A", "email": "a@example.com", "department_id": 1, "assignment_role": "main" }
  ],
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "08:00:00 10/01/2026",
  "updated_at": "10:00:00 01/04/2026"
}
```
