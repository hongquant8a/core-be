# API Người dùng (User) – Core

> Cập nhật lần cuối: 15/07/2026 — bổ sung 7 endpoint chưa được doc (stats/by-organization, self-service `/me`, `/me/profile`, `/{user}/profile`), sửa method `bulk-delete` (POST → DELETE).

Quản lý tài khoản người dùng: thống kê, danh sách, chi tiết, CRUD, xóa/bulk status, đổi trạng thái, xuất/nhập Excel, hồ sơ cá nhân (profile).

**Base path:** `/api/users`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/users/stats` |
| **Query** | `search` (name, email), `status` (active \| inactive \| banned), `from_date` (Y-m-d), `to_date` (Y-m-d), `sort_by`, `sort_order`, `limit` (1-100). Cùng bộ lọc với index. |
| **Response** | `{ "total": 100, "active": 80, "inactive": 20 }` — total (sau lọc), active, inactive (gồm banned). |

---

## Thống kê theo tổ chức

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/users/stats/by-organization` |
| **Permission** | `users.stats` |
| **Query** | `limit` (optional, mặc định 10) — số tổ chức top trả về, cộng bộ lọc chung như index. |
| **Response** | Breakdown số user theo từng tổ chức, dùng cho biểu đồ dashboard. |

---

## Danh sách người dùng

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/users` |
| **Query** | `search` (name, email), `status` (active \| inactive \| banned), `from_date`, `to_date`, `sort_by` (id \| name \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection (UserResource). |

---

## Chi tiết người dùng

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/users/{id}` |
| **UrlParam** | `id` — ID người dùng. |
| **Response** | Object người dùng (UserResource). |

---

## Tạo người dùng

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/users` |
| **Body** | `name` (required), `email` (required, unique), `password` (required, min 6, confirmed), `password_confirmation` (required), `status` (optional: active \| inactive \| banned), `avatar` (optional, file: jpg, png, svg, webp, max 5MB), `assignments` (optional). |
| **Response** | 201, object người dùng + `"message": "Tài khoản đã được tạo thành công!"`. |

**Xử lý avatar:**
- `multipart/form-data` + file blob → upload ảnh mới, trả URL.
- `application/json` + chuỗi rỗng `""` → xóa ảnh, trả `null`.
- Không gửi field avatar → giữ nguyên.

**Mẫu assignments**
```json
[
  { "role_id": 1, "organization_ids": [2, 3] },
  { "role_id": 5, "organization_ids": [9] }
]
```

---

## Cập nhật người dùng

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/users/{id}` |
| **Body** | `name`, `email` (unique nếu đổi), `password` (optional, min 6, confirmed), `password_confirmation`, `status`, `avatar` (optional, file: jpg, png, svg, webp, max 5MB), `assignments` (optional). Khi gửi `assignments`, hệ thống đồng bộ lại toàn bộ phân quyền theo tổ chức của user. |
| **Response** | Object người dùng đã cập nhật. |

**Xử lý avatar:**
- `multipart/form-data` + file blob → upload ảnh mới, xóa ảnh cũ, trả URL mới.
- `application/json` + chuỗi rỗng `""` → xóa ảnh, trả `null`.
- Không gửi field avatar → giữ nguyên.

---

## Xóa người dùng

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/users/{id}` |
| **Response** | `{ "message": "Tài khoản đã được xóa thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/users/bulk-delete` |
| **Body** | `ids` (array) — danh sách ID người dùng. |
| **Response** | `{ "message": "Đã xóa thành công các tài khoản được chọn!" }`. |

---

## Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/users/bulk-status` |
| **Body** | `ids` (array), `status` (required: active \| inactive \| banned). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công" }`. |

---

## Đổi trạng thái người dùng

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/users/{id}/status` |
| **Body** | `status` (required: active \| inactive \| banned). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công!", "data": UserResource }`. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/users/export` |
| **Query** | Cùng bộ lọc với index: `search`, `status`, `from_date`, `to_date`, `sort_by`, `sort_order`. |
| **Response** | File `users.xlsx`. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/users/import` |
| **Body** | `file` (required) — xlsx, xls, csv. Cột bắt buộc: name, email. Cột không bắt buộc: user_name, password (mặc định "password"), status (mặc định "active"). |
| **Response** | `{ "message": "Import người dùng thành công." }`. |

---

## Tải mẫu import

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/users/import-template` |
| **Auth** | Bắt buộc (permission: users.import). |
| **Response** | File `import-users-template.xlsx` — chỉ có header row: `name`, `email`, `user_name`, `password`, `status`. |

---

## Self-service (không cần permission, chỉ cần đăng nhập)

Nhóm endpoint này KHÔNG dùng permission Spatie — Controller tự ép `id = auth()->id()`, user chỉ thao tác được trên chính mình.

### Lấy thông tin bản thân

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/users/me` |
| **Response** | Object người dùng hiện tại (UserResource). |

### Cập nhật thông tin bản thân

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/users/me` |
| **Body** | Giống "Cập nhật người dùng" (trừ `status` — user không tự đổi trạng thái tài khoản của mình được). |
| **Response** | Object người dùng đã cập nhật. |

### Xem hồ sơ cá nhân (profile) của bản thân

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/users/me/profile` |
| **Response** | Object `UserProfile` — gồm các field mở rộng như `telegram_chat_id`. |

### Cập nhật hồ sơ cá nhân (profile) của bản thân

| | |
|---|---|
| **Method** | PUT |
| **Path** | `/api/users/me/profile` |
| **Body** | Field thuộc `UserProfile` (vd `telegram_chat_id` — dùng để nhận thông báo qua kênh Telegram). |
| **Response** | Object `UserProfile` đã cập nhật. |

### Xem hồ sơ cá nhân (profile) của user khác (admin)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/users/{user}/profile` |
| **Permission** | `users.show` |
| **Response** | Object `UserProfile` của user chỉ định. |

### Cập nhật hồ sơ cá nhân (profile) của user khác (admin)

| | |
|---|---|
| **Method** | PUT |
| **Path** | `/api/users/{user}/profile` |
| **Permission** | `users.update` |
| **Body** | Field thuộc `UserProfile`. |
| **Response** | Object `UserProfile` đã cập nhật. |
