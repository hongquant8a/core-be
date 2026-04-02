# API Loại công việc (Task Assignment Item Type)

Quản lý loại công việc trong hệ thống giao việc liên phòng ban: thống kê, danh sách, chi tiết, CRUD, xóa/cập nhật trạng thái hàng loạt, đổi trạng thái, xuất/nhập Excel. Hỗ trợ endpoint công khai không cần xác thực.

**Header bắt buộc (với endpoint cần xác thực):** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** tất cả endpoint có xác thực chỉ thao tác dữ liệu thuộc tổ chức hiện tại (`organization_id` theo `X-Organization-Id`).

**Base path:** `/api/task-assignment-item-types`

---

## Danh sách công khai

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-item-types/public` |
| **Auth** | Không yêu cầu. |
| **Query** | `search` (tên), `status` (active \| inactive), `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | Danh sách loại công việc (không phân trang). |

---

## Danh sách tùy chọn công khai

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-item-types/public-options` |
| **Auth** | Không yêu cầu. |
| **Response** | Mảng `[{ "id": 1, "name": "Nhiệm vụ thường xuyên" }]` — dùng cho dropdown/select. |

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-item-types/stats` |
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên), `status` (active \| inactive), `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | `{ "total": 10, "active": 8, "inactive": 2 }` — total (sau lọc), active = đang hoạt động, inactive = ngừng hoạt động. |

---

## Danh sách loại công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-item-types` |
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên), `status` (active \| inactive), `sort_by` (id \| name \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection; mỗi item gồm đầy đủ các trường của loại công việc. |

---

## Chi tiết loại công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-item-types/{id}` |
| **Auth** | Bắt buộc. |
| **UrlParam** | `id` — ID loại công việc. |
| **Response** | Object loại công việc (TaskAssignmentItemTypeResource). |

---

## Tạo loại công việc

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-item-types` |
| **Auth** | Bắt buộc. |
| **Body** | `name` (required, duy nhất trong tổ chức), `description` (optional), `status` (required: active \| inactive). |
| **Response** | 201, object loại công việc + `"message": "Loại công việc đã được tạo thành công!"`. |

---

## Cập nhật loại công việc

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-item-types/{id}` |
| **Auth** | Bắt buộc. |
| **Body** | Giống tạo (các trường tùy chọn). `name` phải duy nhất trừ bản ghi hiện tại. |
| **Response** | Object loại công việc đã cập nhật. |

---

## Xóa loại công việc

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-item-types/{id}` |
| **Auth** | Bắt buộc. |
| **Response** | `{ "message": "Loại công việc đã được xóa thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-item-types/bulk-delete` |
| **Auth** | Bắt buộc. |
| **Body** | `ids` (array) — danh sách ID loại công việc. |
| **Response** | `{ "message": "Đã xóa thành công các loại công việc được chọn!" }`. |

---

## Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-item-types/bulk-status` |
| **Auth** | Bắt buộc. |
| **Body** | `ids` (array), `status` (required: active \| inactive). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công các loại công việc được chọn!" }`. |

---

## Đổi trạng thái loại công việc

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-item-types/{id}/status` |
| **Auth** | Bắt buộc. |
| **Body** | `status` (required: active \| inactive). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công!", "data": TaskAssignmentItemTypeResource }`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-item-types/export` |
| **Auth** | Bắt buộc. |
| **Query** | Cùng bộ lọc với index: `search`, `status`, `sort_by`, `sort_order`. |
| **Response** | File `task-assignment-item-types.xlsx`. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-item-types/import` |
| **Auth** | Bắt buộc. |
| **Body** | `file` (required) — xlsx, xls, csv. Cột theo chuẩn export. |
| **Response** | `{ "message": "Import loại công việc thành công." }`. |

---

## Response mẫu (TaskAssignmentItemTypeResource)

```json
{
  "id": 1,
  "name": "Nhiệm vụ thường xuyên",
  "description": "Các công việc được giao định kỳ thường xuyên",
  "status": "active",
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "08:00:00 01/04/2026",
  "updated_at": "08:00:00 01/04/2026"
}
```
