# API Role (Core)

Quản lý vai trò (role) theo chuẩn Spatie Laravel Permission: thống kê, danh sách, chi tiết, CRUD, xóa hàng loạt, xuất/nhập Excel. Bảng roles chỉ có các cột mặc định (id, name, guard_name, team_id, timestamps), không có cột status.

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
| **Response** | Paginated collection (RoleResource), mỗi item có `team`, `permissions`. |

---

## Chi tiết role

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/roles/{id}` |
| **UrlParam** | `id` — ID role. |
| **Response** | Object role (RoleResource), kèm `team`, `permissions`. |

---

## Tạo role

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/roles` |
| **Body** | `name` (required), `guard_name` (optional), `team_id` (optional), `permission_ids` (optional, array ID permission). |
| **Response** | 201, object role + `"message": "Vai trò đã được tạo thành công!"`. |

---

## Cập nhật role

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/roles/{id}` |
| **Body** | `name`, `guard_name`, `team_id`, `permission_ids` (sync danh sách quyền). |
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
| **Method** | POST |
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
  "team_id": 1,
  "team": { "id": 1, "name": "Công ty A" },
  "permissions": ["posts.create", "posts.update"],
  "created_at": "14:30:00 17/02/2026",
  "updated_at": "14:30:00 17/02/2026"
}
```
