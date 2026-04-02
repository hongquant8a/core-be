# API Văn bản giao việc (Task Assignment Document)

Quản lý văn bản giao việc liên phòng ban: thống kê, danh sách, chi tiết, CRUD, xóa/cập nhật trạng thái hàng loạt, chuyển trạng thái (nháp/ban hành), xuất/nhập Excel. Hỗ trợ đính kèm tệp và quản lý các công việc con (`items`).

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** tất cả endpoint chỉ thao tác dữ liệu thuộc tổ chức hiện tại (`organization_id` theo `X-Organization-Id`).

**Base path:** `/api/task-assignment-documents`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-documents/stats` |
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên văn bản), `status` (draft \| issued), `task_assignment_type_id`, `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | `{ "total": 30, "active": 20, "inactive": 10 }` — total (sau lọc), active = issued (đã ban hành), inactive = draft (nháp). |

---

## Danh sách văn bản giao việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-documents` |
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên văn bản), `status` (draft \| issued), `task_assignment_type_id` (ID loại văn bản), `sort_by` (id \| name \| issue_date \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection; mỗi item gồm đầy đủ các trường kèm `items_count`. |

---

## Chi tiết văn bản giao việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-documents/{id}` |
| **Auth** | Bắt buộc. |
| **UrlParam** | `id` — ID văn bản. |
| **Response** | Object văn bản (TaskAssignmentDocumentResource), kèm `attachments`, `items_count`. |

---

## Tạo văn bản giao việc

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-documents` |
| **Auth** | Bắt buộc. |
| **Body** | `name` (required), `summary` (optional), `issue_date` (optional, định dạng YYYY-MM-DD), `task_assignment_type_id` (required, ID loại văn bản), `status` (required: draft \| issued), `attachments[]` (optional, tệp đính kèm, có thể nhiều file). Form-data hoặc JSON. |
| **Response** | 201, object văn bản (kèm attachments) + `"message": "Văn bản giao việc đã được tạo thành công!"`. |

---

## Cập nhật văn bản giao việc

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-documents/{id}` |
| **Auth** | Bắt buộc. |
| **Body** | Giống tạo (các trường tùy chọn). Thêm: `remove_attachment_ids` (mảng ID tệp đính kèm cần xóa), `attachments[]` (tệp mới append). |
| **Response** | Object văn bản đã cập nhật (kèm attachments). |

---

## Xóa văn bản giao việc

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-documents/{id}` |
| **Auth** | Bắt buộc. |
| **Response** | `{ "message": "Văn bản giao việc đã được xóa thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-documents/bulk-delete` |
| **Auth** | Bắt buộc. |
| **Body** | `ids` (array) — danh sách ID văn bản. |
| **Response** | `{ "message": "Đã xóa thành công các văn bản giao việc được chọn!" }`. |

---

## Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-documents/bulk-status` |
| **Auth** | Bắt buộc. |
| **Body** | `ids` (array), `status` (required: draft \| issued). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công các văn bản giao việc được chọn!" }`. |

---

## Đổi trạng thái văn bản (nháp / ban hành)

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-documents/{id}/status` |
| **Auth** | Bắt buộc. |
| **Body** | `status` (required: draft \| issued). Chuyển sang `issued` sẽ tự động ghi nhận `issued_at`. |
| **Response** | `{ "message": "Cập nhật trạng thái thành công!", "data": TaskAssignmentDocumentResource }`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-documents/export` |
| **Auth** | Bắt buộc. |
| **Query** | Cùng bộ lọc với index: `search`, `status`, `task_assignment_type_id`, `sort_by`, `sort_order`. |
| **Response** | File `task-assignment-documents.xlsx`. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-documents/import` |
| **Auth** | Bắt buộc. |
| **Body** | `file` (required) — xlsx, xls, csv. Cột theo chuẩn export. |
| **Response** | `{ "message": "Import văn bản giao việc thành công." }`. |

---

## Response mẫu (TaskAssignmentDocumentResource)

```json
{
  "id": 1,
  "name": "Quyết định số 01/QĐ-HĐQT về giao nhiệm vụ Q1/2026",
  "summary": "Giao nhiệm vụ cho các phòng ban trong quý 1 năm 2026",
  "issue_date": "2026-01-10",
  "task_assignment_type_id": 1,
  "task_assignment_type": { "id": 1, "name": "Quyết định" },
  "status": "issued",
  "issued_at": "09:00:00 10/01/2026",
  "items_count": 5,
  "attachments": [
    { "id": 1, "name": "quyet-dinh-01.pdf", "url": "https://..." }
  ],
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "09:00:00 10/01/2026",
  "updated_at": "09:00:00 10/01/2026"
}
```
