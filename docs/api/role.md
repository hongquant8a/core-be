# API Role (Core)

> Cập nhật lần cuối: 16:02:55 15/07/2026 — sửa team_id → organization_id (client không tự set), permission_ids là tên permission (string) không phải ID, bổ sung organization/users_count vào response, sửa method bulk-delete thành DELETE.

Quản lý vai trò (role) theo chuẩn Spatie Laravel Permission: thống kê, danh sách, chi tiết, CRUD, xóa hàng loạt, xuất/nhập Excel. Bảng roles có cột `organization_id` nhưng route CRUD hiện tại luôn hardcode giá trị này là `null` khi tạo/cập nhật — client không tự set tenant scope qua API này được. Không có cột status.

**Base path:** `/api/roles`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/roles/stats` |
| **Query** | `search` (name, guard_name), `from_date` (Y-m-d), `to_date` (Y-m-d), `sort_by`, `sort_order`, `limit` (1-100). Cùng bộ lọc với index. |
| **Response** | `{ "total": 20 }`. |

---

## Danh sách role

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/roles` |
| **Query** | `search`, `from_date`, `to_date`, `sort_by` (id \| name \| guard_name \| created_at \| updated_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection (RoleResource), mỗi item có `organization`, `permissions`, `users_count`. |

---

## Chi tiết role

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/roles/{id}` |
| **UrlParam** | `id` — ID role. |
| **Response** | Object role (RoleResource), kèm `organization`, `permissions`, `users_count`. |

---

## Tạo role

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/roles` |
| **Body** | `name` (required), `guard_name` (optional), `permission_ids` (optional, array **tên** permission, vd `["users.index", "users.store"]` — không phải ID số). `organization_id` KHÔNG nhận từ client, server luôn set `null`. |
| **Response** | 201, object role + `"message": "Vai trò đã được tạo thành công!"`. |

---

## Cập nhật role

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/roles/{id}` |
| **Body** | `name`, `guard_name`, `permission_ids` (array **tên** permission, sync danh sách quyền — không phải ID số). `organization_id` KHÔNG nhận từ client, server luôn set `null`. |
| **Response** | Object role + `"message": "Vai trò đã được cập nhật!"`. |

---

## Xóa role

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/roles/{id}` |
| **Response** | `{ "message": "Vai trò đã được xóa!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/roles/bulk-delete` |
| **Body** | `ids` (array) — danh sách ID role. |
| **Response** | `{ "message": "Đã xóa thành công các vai trò được chọn!" }`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/roles/export` |
| **Query** | Cùng bộ lọc với index: search, from_date, to_date, sort_by, sort_order, limit. |
| **Response** | File `roles.xlsx`. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/roles/import` |
| **Body** | `file` (required) — xlsx, xls, csv. Cột: name, guard_name, organization_id. |
| **Response** | `{ "message": "Import vai trò thành công." }`. |

---

## Tải mẫu import

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/roles/import-template` |
| **Auth** | Bắt buộc (permission: roles.import). |
| **Response** | File `import-roles-template.xlsx` — chỉ có header row: `name`, `guard_name`, `organization_id`. |

---

## Response mẫu (RoleResource)

```json
{
  "id": 1,
  "name": "admin",
  "guard_name": "web",
  "organization_id": null,
  "organization": null,
  "permissions": ["posts.create", "posts.update"],
  "users_count": 5,
  "created_at": "14:30:00 17/02/2026",
  "updated_at": "14:30:00 17/02/2026"
}
```

> `organization_id`/`organization` hiện luôn `null` vì `RoleService::store`/`update` hardcode `organization_id = null` — API này chưa hỗ trợ client tự gán tenant scope cho role.
