# API Phòng ban giao việc (Task Assignment Department)

> Cập nhật lần cuối: 24/08/2026 — tái cấu trúc quan hệ nhân viên ↔ phòng ban: bỏ 3 endpoint `/{id}/users` (danh sách/đồng bộ/xóa) cùng 3 quyền `users`/`syncUsers`/`removeUser`; thành viên nay là trường `employee_ids` của chính form phòng ban. Mỗi endpoint gác một permission riêng (đủ 11). `users_count` đổi thành `employees_count`.

Quản lý phòng ban trong hệ thống giao việc liên phòng ban: thống kê, danh sách, chi tiết, CRUD, xóa/cập nhật trạng thái hàng loạt, đổi trạng thái, xuất/nhập Excel, quản lý user thuộc phòng ban. Hỗ trợ endpoint công khai không cần xác thực để lấy danh sách phòng ban.

**Header bắt buộc (với endpoint cần xác thực):** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** tất cả endpoint có xác thực chỉ thao tác dữ liệu thuộc tổ chức hiện tại (`organization_id` theo `X-Organization-Id`).

**Base path:** `/api/task-assignment-departments`

---

## Danh sách công khai

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/public/task-assignment-departments` |
| **Auth** | Không yêu cầu. |
| **Query** | `search` (tên phòng ban), `status` (active \| inactive), `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | Danh sách phòng ban (không phân trang). |

---

## Danh sách tùy chọn công khai

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/public/task-assignment-departments/options` |
| **Auth** | Không yêu cầu (dành cho citizen/guest). |
| **Response** | Mảng `[{ "id": 1, "name": "Phòng Kỹ thuật" }]` — dùng cho dropdown/select. |

---

## Dropdown options (authenticated, không Spatie)

Dành cho form admin (giao task, gán nhân viên, ...). Khác `/public/...options`: cần Bearer token nhưng KHÔNG cần permission `task-assignment-departments.index`.

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/options` |
| **Auth** | Bắt buộc (Bearer + `X-Organization-Id`). KHÔNG qua Spatie. |
| **Query** | `search` (tên), `sort_by`, `sort_order`. |
| **Response** | Mảng `[{ "id": 1, "name": "Phòng Kỹ thuật", "description": "..." }]`. |

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/stats` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.stats`). |
| **Query** | `search` (tên), `status` (active \| inactive), `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | `{ "total": 20, "active": 15, "inactive": 5 }` — total (sau lọc), active = đang hoạt động, inactive = ngừng hoạt động. |

---

## Danh sách phòng ban

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.index`). |
| **Query** | `search` (tên), `status` (active \| inactive), `sort_by` (id \| name \| sort_order \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection; mỗi item gồm đầy đủ các trường của phòng ban. |

---

## Chi tiết phòng ban

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/{id}` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.show`). |
| **UrlParam** | `id` — ID phòng ban. |
| **Response** | Object phòng ban (TaskAssignmentDepartmentResource). |

---

## Tạo phòng ban

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-departments` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.store`). |
| **Body** | `name` (required, max 255 — KHÔNG có rule unique ở tầng validation), `description` (optional), `status` (required: active \| inactive), `sort_order` (optional, số nguyên >= 0), `is_petition_overview` (optional, boolean — phòng ban tổng hợp đơn thư, được xem toàn bộ đơn thư của mọi phòng ban khác). |
| **Body (quan hệ)** | `employee_ids` (optional, mảng ID nhân viên — `task_assignment_employees.id`; gửi mảng rỗng để xóa hết thành viên, không gửi thì giữ nguyên), `representative_employee_id` (optional, phải nằm trong `employee_ids`). |
| **Response** | 201, object phòng ban + `"message": "Phòng ban đã được tạo thành công!"`. |

---

## Cập nhật phòng ban

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-departments/{id}` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.update`). |
| **Body** | Giống tạo (các trường tùy chọn). |
| **Body (quan hệ)** | `employee_ids` (optional, mảng ID nhân viên — `task_assignment_employees.id`; gửi mảng rỗng để xóa hết thành viên, không gửi thì giữ nguyên), `representative_employee_id` (optional, phải nằm trong `employee_ids`). |
| **Response** | Object phòng ban đã cập nhật. |

---

## Xóa phòng ban

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-departments/{id}` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.destroy`). |
| **Response** | `{ "message": "Phòng ban đã được xóa thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-departments/bulk-delete` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.bulkDestroy`). |
| **Body** | `ids` (array) — danh sách ID phòng ban. |
| **Response** | `{ "message": "Đã xóa thành công các phòng ban được chọn!" }`. |

---

## Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-departments/bulk-status` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.bulkUpdateStatus`). |
| **Body** | `ids` (array), `status` (required: active \| inactive). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công các phòng ban được chọn!" }`. |

---

## Đổi trạng thái phòng ban

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-departments/{id}/status` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.changeStatus`). |
| **Body** | `status` (required: active \| inactive). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công!", "data": TaskAssignmentDepartmentResource }`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/export` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.export`). |
| **Query** | Cùng bộ lọc với index: `search`, `status`, `sort_by`, `sort_order`. |
| **Response** | File `task-assignment-departments.xlsx`. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-departments/import` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.import`). |
| **Body** | `file` (required) — xlsx, xls, csv. Cột theo chuẩn export. |
| **Response** | `{ "message": "Import phòng ban thành công." }`. |

---

## Tải mẫu import

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/import-template` |
| **Auth** | Bắt buộc (permission: `task-assignment-departments.import`). |
| **Response** | File `import-departments-template.xlsx` — chỉ có header row: `name`, `description`, `status`, `sort_order`. |

---

## Response mẫu (DepartmentResource)

```json
{
  "id": 1,
  "name": "Phòng Kỹ thuật",
  "description": "Phòng phụ trách kỹ thuật hệ thống",
  "status": "active",
  "sort_order": 1,
  "is_petition_overview": false,
  "employees_count": 8,
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "08:00:00 01/04/2026",
  "updated_at": "08:00:00 01/04/2026"
}
```

`users_count` chỉ xuất hiện khi endpoint eager-load count quan hệ (`withCount`) — không phải mọi response đều có field này.
