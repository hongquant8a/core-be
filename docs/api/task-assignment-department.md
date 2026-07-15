# API Phòng ban giao việc (Task Assignment Department)

> Cập nhật lần cuối: 15/07/2026 — xóa field `code` đã bị gỡ khỏi bảng (migration `drop_code_from_task_assignment_departments_table`, 26/05/2026), sửa path public (`/api/public/...`), sửa method `bulk-delete`, bổ sung `is_petition_overview`/`users_count`, bổ sung 3 endpoint quản lý user trong phòng ban.

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
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên), `status` (active \| inactive), `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | `{ "total": 20, "active": 15, "inactive": 5 }` — total (sau lọc), active = đang hoạt động, inactive = ngừng hoạt động. |

---

## Danh sách phòng ban

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments` |
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên), `status` (active \| inactive), `sort_by` (id \| name \| sort_order \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
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
| **Body** | `name` (required, max 255 — KHÔNG có rule unique ở tầng validation), `description` (optional), `status` (required: active \| inactive), `sort_order` (optional, số nguyên >= 0), `is_petition_overview` (optional, boolean — phòng ban tổng hợp đơn thư, được xem toàn bộ đơn thư của mọi phòng ban khác). |
| **Response** | 201, object phòng ban + `"message": "Phòng ban đã được tạo thành công!"`. |

---

## Cập nhật phòng ban

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-departments/{id}` |
| **Auth** | Bắt buộc. |
| **Body** | Giống tạo (các trường tùy chọn). |
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
| **Method** | DELETE |
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

## Tải mẫu import

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/import-template` |
| **Auth** | Bắt buộc (permission: import). |
| **Response** | File `import-departments-template.xlsx` — chỉ có header row: `name`, `description`, `status`, `sort_order`. |

---

## Danh sách user trong phòng ban

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-departments/{id}/users` |
| **Auth** | Bắt buộc (không cần permission riêng — Bearer + `X-Organization-Id`). |
| **Response** | Mảng `[{ "id": 1, "user_id": 5, "name": "Nguyễn Văn A", "email": "...", "user_name": "...", "avatar": "/storage/.../avatar.jpg", "status": "active", "is_representative": true }]`. |

---

## Đồng bộ user trong phòng ban

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-departments/{id}/users` |
| **Permission** | `task-assignment-departments.syncUsers` |
| **Body** | `user_ids` (required, array ID user), `representative_user_id` (optional, ID người đại diện — phải nằm trong `user_ids`). |
| **Response** | `{ "message": "Đồng bộ người dùng thành công!" }`. Đồng bộ toàn bộ danh sách user của phòng ban theo `user_ids` gửi lên (thay thế, không phải thêm). |

---

## Xóa user khỏi phòng ban

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-departments/{id}/users/{userId}` |
| **Permission** | `task-assignment-departments.removeUser` |
| **Response** | `{ "message": "Xóa người dùng khỏi phòng ban thành công!" }`. |

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
  "users_count": 8,
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "08:00:00 01/04/2026",
  "updated_at": "08:00:00 01/04/2026"
}
```

`users_count` chỉ xuất hiện khi endpoint eager-load count quan hệ (`withCount`) — không phải mọi response đều có field này.
