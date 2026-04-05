# API Permission (Core)

Quản lý quyền (permission): thống kê, danh sách, cây, chi tiết, CRUD, xóa hàng loạt, xuất/nhập Excel. Bổ sung description, sort_order, parent_id để nhóm và sắp xếp hiển thị trên frontend.

**Base path:** `/api/permissions`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/permissions/stats` |
| **Query** | `search` (name, guard_name, description), `from_date` (Y-m-d), `to_date` (Y-m-d), `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | `{ "total": 50 }`. |

---

## Danh sách permission

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/permissions` |
| **Query** | `search`, `from_date`, `to_date`, `sort_by` (id \| name \| guard_name \| description \| sort_order \| parent_id \| created_at \| updated_at), `sort_order` (asc \| desc), `limit` (1-100). Thứ tự mặc định theo cây (treeOrder). |
| **Response** | Paginated collection (PermissionResource), mỗi item có `parent`. |

---

## Cây permission

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/permissions/tree` |
| **Query** | `parent_id` (optional, null = gốc). |
| **Response** | Mảng cây (không phân trang), mỗi node có `children` đệ quy — dùng hiển thị nhóm quyền trên frontend. |

---

## Chi tiết permission

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/permissions/{id}` |
| **UrlParam** | `id` — ID permission. |
| **Response** | Object permission (PermissionResource), kèm `parent`, `children`. |

---

## Tạo permission

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/permissions` |
| **Body** | `name` (required), `guard_name` (optional), `description` (optional), `sort_order` (optional), `parent_id` (optional, null = gốc). |
| **Response** | 201, object permission + `"message": "Quyền đã được tạo thành công!"`. |

---

## Cập nhật permission

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/permissions/{id}` |
| **Body** | `name`, `guard_name`, `description`, `sort_order`, `parent_id`. |
| **Response** | Object permission + `"message": "Quyền đã được cập nhật!"`. |

---

## Xóa permission

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/permissions/{id}` |
| **Response** | `{ "message": "Quyền đã được xóa!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/permissions/bulk-delete` |
| **Body** | `ids` (array) — danh sách ID permission. |
| **Response** | `{ "message": "Đã xóa thành công các quyền được chọn!" }`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/permissions/export` |
| **Query** | Cùng bộ lọc với index. |
| **Response** | File `permissions.xlsx`. Cột: ID, Name, Guard Name, Description, Sort Order, Parent ID, Created At, Updated At. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/permissions/import` |
| **Body** | `file` (required) — xlsx, xls, csv. Cột: name, guard_name, description, sort_order, parent_id. |
| **Response** | `{ "message": "Import quyền thành công." }`. |

---

## Tải mẫu import

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/permissions/import-template` |
| **Auth** | Bắt buộc (permission: permissions.import). |
| **Response** | File `import-permissions-template.xlsx` — chỉ có header row: `name`, `guard_name`, `description`, `sort_order`, `parent_id`. |

---

## Response mẫu (PermissionResource)

```json
{
  "id": 1,
  "name": "posts.create",
  "guard_name": "web",
  "description": "Bài viết - Tạo mới",
  "sort_order": 3,
  "parent_id": 50,
  "parent": { "id": 50, "name": "group:posts" },
  "children": [],
  "created_at": "14:30:00 17/02/2026",
  "updated_at": "14:30:00 17/02/2026"
}
```
