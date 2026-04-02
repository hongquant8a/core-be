# API Công việc (Task Assignment Item)

Quản lý công việc trong hệ thống giao việc liên phòng ban: thống kê, danh sách (với bộ lọc nâng cao), chi tiết, CRUD, xóa/cập nhật trạng thái hàng loạt, đổi trạng thái, cập nhật tiến độ, xuất/nhập Excel. Mỗi công việc có thể giao cho nhiều phòng ban và nhiều người dùng (quan hệ nhiều-nhiều).

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** tất cả endpoint chỉ thao tác dữ liệu thuộc tổ chức hiện tại (`organization_id` theo `X-Organization-Id`).

**Base path:** `/api/task-assignment-items`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats` |
| **Auth** | Bắt buộc. |
| **Query** | `search`, `processing_status`, `priority`, `deadline_type`, `department_id`, `user_id`, `start_at_from`, `start_at_to`, `end_at_from`, `end_at_to`, `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | `{ "total": 100, "active": 60, "inactive": 40 }` — total (sau lọc), active = đang xử lý, inactive = hoàn thành hoặc quá hạn. |

---

## Danh sách công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items` |
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên công việc), `processing_status` (pending \| in_progress \| completed \| overdue), `priority` (low \| medium \| high \| urgent), `deadline_type` (fixed \| flexible \| no_deadline), `department_id` (ID phòng ban được giao), `user_id` (ID người dùng được giao), `task_assignment_document_id` (ID văn bản giao việc), `start_at_from` / `start_at_to` (YYYY-MM-DD), `end_at_from` / `end_at_to` (YYYY-MM-DD), `sort_by` (id \| name \| start_at \| end_at \| priority \| completion_percent \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
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
| **Body** | `name` (required), `description` (optional), `task_assignment_document_id` (required, ID văn bản), `task_assignment_item_type_id` (optional, ID loại công việc), `deadline_type` (required: fixed \| flexible \| no_deadline), `start_at` (optional, YYYY-MM-DD HH:mm:ss), `end_at` (optional, YYYY-MM-DD HH:mm:ss — bắt buộc nếu `deadline_type` = fixed), `processing_status` (required: pending \| in_progress \| completed \| overdue), `completion_percent` (optional, 0-100), `priority` (required: low \| medium \| high \| urgent), `departments` (optional, mảng ID phòng ban), `users` (optional, mảng ID người dùng). |
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
| **Body** | `ids` (array), `processing_status` (required: pending \| in_progress \| completed \| overdue). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công các công việc được chọn!" }`. |

---

## Đổi trạng thái công việc

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/status` |
| **Auth** | Bắt buộc. |
| **Body** | `processing_status` (required: pending \| in_progress \| completed \| overdue). Chuyển sang `completed` sẽ tự động ghi nhận `completed_at`. |
| **Response** | `{ "message": "Cập nhật trạng thái thành công!", "data": TaskAssignmentItemResource }`. |

---

## Cập nhật tiến độ

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/progress` |
| **Auth** | Bắt buộc. |
| **Body** | `completion_percent` (required, 0-100). |
| **Response** | `{ "message": "Cập nhật tiến độ thành công!", "data": TaskAssignmentItemResource }`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/export` |
| **Auth** | Bắt buộc. |
| **Query** | Cùng bộ lọc với index: `search`, `processing_status`, `priority`, `deadline_type`, `department_id`, `user_id`, `start_at_from`, `start_at_to`, `end_at_from`, `end_at_to`, `sort_by`, `sort_order`. |
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
  "deadline_type": "fixed",
  "start_at": "2026-01-10 08:00:00",
  "end_at": "2026-03-31 17:00:00",
  "processing_status": "in_progress",
  "completion_percent": 45,
  "priority": "high",
  "completed_at": null,
  "departments": [
    { "id": 1, "name": "Phòng Nhân sự", "code": "NS" }
  ],
  "users": [
    { "id": 2, "name": "Nguyễn Văn A", "email": "a@example.com" }
  ],
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "08:00:00 10/01/2026",
  "updated_at": "10:00:00 01/04/2026"
}
```
