# API Organization (Core)

Quản lý organization (tổ chức) phân cấp theo `parent_id`: thống kê, danh sách, cây, CRUD, xóa/bulk status, đổi trạng thái, xuất/nhập Excel.

**Base path:** `/api/organizations`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/organizations/stats` |
| **Query** | `search` (name, slug), `status` (active \| inactive), `from_date` (Y-m-d), `to_date` (Y-m-d), `sort_by`, `sort_order`, `limit` (1-100). Cùng bộ lọc với index. |
| **Response** | `{ "total": 10, "active": 8, "inactive": 2 }` |

---

## Danh sách organization

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/organizations` |
| **Query** | `search`, `status`, `from_date`, `to_date`, `sort_by` (id \| name \| slug \| status \| created_at \| updated_at), `sort_order` (asc \| desc), `limit` (1-100). Thứ tự theo cây (treeOrder). |
| **Response** | Paginated collection (OrganizationResource), mỗi item có `creator`, `editor`, `parent`. |

---

## Danh sách organization công khai

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/organizations/public` |
| **Auth** | Không cần |
| **Query** | `search` (name, slug). Chỉ trả dữ liệu `active`, sắp xếp theo thứ tự cây. |
| **Response** | Collection không phân trang (OrganizationResource), phù hợp cho dropdown/chọn organization. |

---

## Danh sách organization công khai (dropdown tối ưu)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/organizations/public-options` |
| **Auth** | Không cần |
| **Query** | `search` (name, slug). Chỉ trả dữ liệu `active`, sắp xếp theo thứ tự cây. |
| **Response** | Collection không phân trang với 3 trường: `id`, `name`, `description`. |

---

## Cây organization

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/organizations/tree` |
| **Query** | `status` (active \| inactive). |
| **Response** | Mảng cây (không phân trang), mỗi node có `children` đệ quy — OrganizationTreeResource. |

---

## Chi tiết organization

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/organizations/{id}` |
| **UrlParam** | `id` — ID organization. |
| **Response** | Object organization (OrganizationResource), kèm `parent`, `children`. |

---

## Tạo organization

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/organizations` |
| **Body** | `name` (required), `slug` (optional, tự sinh từ name), `description` (optional), `status` (required: active \| inactive), `parent_id` (optional, null = gốc), `sort_order` (optional). |
| **Response** | 201, object organization + `"message": "Organization đã được tạo thành công!"`. |

---

## Cập nhật organization

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/organizations/{id}` |
| **Body** | `name`, `slug`, `description`, `status`, `parent_id` (null hoặc 0 = gốc), `sort_order`. Không được chọn organization con làm organization cha. |
| **Response** | Object organization + `"message": "Organization đã được cập nhật!"`. |

---

## Xóa organization

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/organizations/{id}` |
| **Response** | `{ "message": "Organization đã được xóa!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/organizations/bulk-delete` |
| **Body** | `ids` (array) — danh sách ID organization. |
| **Response** | `{ "message": "Đã xóa thành công các organization được chọn!" }`. |

---

## Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/organizations/bulk-status` |
| **Body** | `ids` (array), `status` (required: active \| inactive). |
| **Response** | `{ "message": "Cập nhật trạng thái organization thành công." }`. |

---

## Đổi trạng thái organization

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/organizations/{id}/status` |
| **Body** | `status` (required: active \| inactive). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công!", "data": OrganizationResource }`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/organizations/export` |
| **Query** | Cùng bộ lọc với index: `search`, `status`, `from_date`, `to_date`, `sort_by`, `sort_order`. |
| **Response** | File `organizations.xlsx`. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/organizations/import` |
| **Body** | `file` (required) — xlsx, xls, csv. Cột bắt buộc: name, slug, description, status. Cột không bắt buộc: sort_order, parent_slug (khớp với header của "Tải mẫu import" bên dưới). |
| **Response** | `{ "message": "Import organization thành công." }`. |

---

## Tải mẫu import

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/organizations/import-template` |
| **Auth** | Bắt buộc (permission: organizations.import). |
| **Response** | File `import-organizations-template.xlsx` — chỉ có header row: `name`, `slug`, `description`, `status`, `sort_order`, `parent_slug`. |

---

## Response mẫu (OrganizationResource)

```json
{
  "id": 1,
  "name": "Công ty A",
  "slug": "cong-ty-a",
  "description": "Mô tả organization",
  "status": "active",
  "parent_id": null,
  "sort_order": 0,
  "depth": 0,
  "created_by": "Admin",
  "updated_by": "Admin",
  "created_at": "14:30:00 17/02/2026",
  "updated_at": "14:30:00 17/02/2026"
}
```
