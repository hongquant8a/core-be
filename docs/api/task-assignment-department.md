# API Phòng ban giao việc (Task Assignment Department)

Quản lý phòng ban trong hệ thống giao việc liên phòng ban: thống kê, danh sách, chi tiết, CRUD, xóa/cập nhật trạng thái hàng loạt, đổi trạng thái, xuất/nhập Excel. Hỗ trợ endpoint công khai không cần xác thực để lấy danh sách phòng ban.

**Header bắt buộc (với endpoint cần xác thực):** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** tất cả endpoint có xác thực chỉ thao tác dữ liệu thuộc tổ chức hiện tại (`organization_id` theo `X-Organization-Id`).

**Base path:** `/api/task-assignment-departments`

---

## Danh sách công khai

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/public` |
| **Auth** | Không yêu cầu. |
| **Query** | `search` (tên/mã phòng ban), `status` (active \| inactive), `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | Danh sách phòng ban (không phân trang). |

---

## Danh sách tùy chọn công khai

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/public-options` |
| **Auth** | Không yêu cầu. |
| **Response** | Mảng `[{ "id": 1, "name": "Phòng Kỹ thuật", "code": "KT" }]` — dùng cho dropdown/select. |

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/stats` |
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên/mã), `status` (active \| inactive), `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | `{ "total": 20, "active": 15, "inactive": 5 }` — total (sau lọc), active = đang hoạt động, inactive = ngừng hoạt động. |

---

## Danh sách phòng ban

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments` |
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên/mã), `status` (active \| inactive), `sort_by` (id \| name \| code \| sort_order \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection; mỗi item gồm đầy đủ các trường của phòng ban. |

---

## Chi tiết phòng ban

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/{id}` |
| **Auth** | Bắt buộc. |
| **UrlParam** | `id` — ID phòng ban. |
| **Response** | Object phòng ban (TaskAssignmentDepartmentResource). |

---

## Tạo phòng ban

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-departments` |
| **Auth** | Bắt buộc. |
| **Body** | `code` (required, duy nhất trong tổ chức), `name` (required), `description` (optional), `status` (required: active \| inactive), `sort_order` (optional, số nguyên). |
| **Response** | 201, object phòng ban + `"message": "Phòng ban đã được tạo thành công!"`. |

---

## Cập nhật phòng ban

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-departments/{id}` |
| **Auth** | Bắt buộc. |
| **Body** | Giống tạo (các trường tùy chọn). `code` phải duy nhất trừ bản ghi hiện tại. |
| **Response** | Object phòng ban đã cập nhật. |

---

## Xóa phòng ban

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-departments/{id}` |
| **Auth** | Bắt buộc. |
| **Response** | `{ "message": "Phòng ban đã được xóa thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-departments/bulk-delete` |
| **Auth** | Bắt buộc. |
| **Body** | `ids` (array) — danh sách ID phòng ban. |
| **Response** | `{ "message": "Đã xóa thành công các phòng ban được chọn!" }`. |

---

## Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-departments/bulk-status` |
| **Auth** | Bắt buộc. |
| **Body** | `ids` (array), `status` (required: active \| inactive). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công các phòng ban được chọn!" }`. |

---

## Đổi trạng thái phòng ban

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-departments/{id}/status` |
| **Auth** | Bắt buộc. |
| **Body** | `status` (required: active \| inactive). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công!", "data": TaskAssignmentDepartmentResource }`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/export` |
| **Auth** | Bắt buộc. |
| **Query** | Cùng bộ lọc với index: `search`, `status`, `sort_by`, `sort_order`. |
| **Response** | File `task-assignment-departments.xlsx`. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-departments/import` |
| **Auth** | Bắt buộc. |
| **Body** | `file` (required) — xlsx, xls, csv. Cột theo chuẩn export. |
| **Response** | `{ "message": "Import phòng ban thành công." }`. |

---

## Response mẫu (TaskAssignmentDepartmentResource)

```json
{
  "id": 1,
  "code": "KT",
  "name": "Phòng Kỹ thuật",
  "description": "Phòng phụ trách kỹ thuật hệ thống",
  "status": "active",
  "sort_order": 1,
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "08:00:00 01/04/2026",
  "updated_at": "08:00:00 01/04/2026"
}
```
