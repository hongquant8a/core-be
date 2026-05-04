# API Danh mục module Meeting (Catalogs)

Tài liệu API cho FE implement các trang quản lý danh mục của module Cuộc họp:

| Danh mục | Base path | Tên hiển thị |
|---|---|---|
| Loại cuộc họp | `/api/meeting-types` | Meeting Type |
| Địa điểm họp | `/api/meeting-locations` | Meeting Location |
| Loại tài liệu họp | `/api/meeting-document-types` | Meeting Document Type |
| Nhóm đại biểu | `/api/meeting-attendee-groups` | Meeting Attendee Group |
| Đại biểu | `/api/meeting-attendees` | Meeting Attendee |

**Header bắt buộc (endpoint cần xác thực):**
- `Authorization: Bearer {token}`
- `X-Organization-Id: {organization_id}` — ID tổ chức làm việc.

**Phạm vi dữ liệu:** mọi endpoint có xác thực chỉ thao tác bản ghi của tổ chức hiện tại (theo `X-Organization-Id`).

**Quyền (Spatie Permission):** mỗi action đều check `permission:{resource}.{action}` (ví dụ `meeting-types.index`, `meeting-attendees.bulkDestroy`).

**Response envelope:**

```json
// Index/list
{ "success": true, "data": { "items": [...], "pagination": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 27 } } }

// Show/store/update/changeStatus
{ "success": true, "message": "...", "data": { ... } }

// Stats / destroy / bulk
{ "success": true, "message": "...", "data": null | {...} }

// Error
{ "success": false, "message": "...", "code": "VALIDATION_ERROR | FORBIDDEN | NOT_FOUND | ...", "errors": { "field": ["..."] } }
```

Tất cả `created_at` / `updated_at` được trả về dạng `H:i:s d/m/Y` (ví dụ `08:30:00 01/05/2026`).

---

## 1. Loại cuộc họp — `/api/meeting-types`

Có endpoint công khai (không cần auth) để FE landing/portal dùng.

### 1.1 Public

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-types/public` | Danh sách (không phân trang), chỉ status=active. Query: `search`, `sort_by`, `sort_order`. |
| GET | `/api/meeting-types/public-options` | Dropdown tối giản `[{id, name, description}]`. Sắp xếp theo `name asc`. |

### 1.2 Authenticated CRUD

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-types/stats` | `{ total, active, inactive }`. Query: `search`, `status`. |
| GET | `/api/meeting-types` | Danh sách phân trang. Query: `search`, `status`, `sort_by` (id\|name\|created_at), `sort_order`, `limit` (1-100). |
| GET | `/api/meeting-types/{id}` | Chi tiết. |
| POST | `/api/meeting-types` | Tạo mới. Body: xem [Catalog body](#catalog-body). |
| PUT \| PATCH | `/api/meeting-types/{id}` | Cập nhật. |
| DELETE | `/api/meeting-types/{id}` | Xóa. |
| POST | `/api/meeting-types/bulk-delete` | Body `{ "ids": [1,2,3] }`. |
| PATCH | `/api/meeting-types/bulk-status` | Body `{ "ids": [...], "status": "active\|inactive" }`. |
| PATCH | `/api/meeting-types/{id}/status` | Body `{ "status": "active\|inactive" }`. |
| GET | `/api/meeting-types/export` | Tải Excel `meeting-types.xlsx`. Query giống index. |
| POST | `/api/meeting-types/import` | `multipart/form-data` field `file` (xlsx\|xls\|csv ≤10MB). Xem [Import](#import). |
| GET | `/api/meeting-types/import-template` | Tải file mẫu `import-meeting-types-template.xlsx` (chỉ header). |

### 1.3 Response item (CatalogResource)

```json
{
  "id": 1,
  "organization_id": 1,
  "name": "Họp giao ban",
  "description": "Họp giao ban định kỳ tuần",
  "address": null,
  "google_maps_url": null,
  "status": "active",
  "created_by": "Admin",
  "updated_by": "Admin",
  "created_at": "08:00:00 01/05/2026",
  "updated_at": "08:00:00 01/05/2026"
}
```

> Các trường `address`, `google_maps_url` luôn được trả về (null nếu không áp dụng) — chỉ `meeting-locations` mới có giá trị thực.

---

## 2. Địa điểm họp — `/api/meeting-locations`

Có endpoint public + public-options giống mục 1.

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-locations/public` | Danh sách công khai. |
| GET | `/api/meeting-locations/public-options` | Dropdown `[{id, name, description}]`. |
| GET | `/api/meeting-locations/stats` | `{ total, active, inactive }`. |
| GET | `/api/meeting-locations` | Danh sách phân trang. Query giống mục 1.2. |
| GET | `/api/meeting-locations/{id}` | Chi tiết. |
| POST | `/api/meeting-locations` | Tạo. Body: [Catalog body](#catalog-body) — **địa điểm dùng cả `address`, `google_maps_url`**. |
| PUT \| PATCH | `/api/meeting-locations/{id}` | Cập nhật. |
| DELETE | `/api/meeting-locations/{id}` | Xóa. |
| POST | `/api/meeting-locations/bulk-delete` | Body `{ "ids": [...] }`. |
| PATCH | `/api/meeting-locations/bulk-status` | Body `{ "ids": [...], "status": "active\|inactive" }`. |
| PATCH | `/api/meeting-locations/{id}/status` | Body `{ "status": "active\|inactive" }`. |
| GET | `/api/meeting-locations/export` | Tải Excel `meeting-locations.xlsx`. |
| POST | `/api/meeting-locations/import` | `multipart/form-data` field `file`. Hỗ trợ cột địa lý. |
| GET | `/api/meeting-locations/import-template` | Tải file mẫu `import-meeting-locations-template.xlsx` (kèm cột địa lý). |

Response: cùng `CatalogResource` (xem 1.3) — các trường địa lý có giá trị thực.

---

## 3. Loại tài liệu họp — `/api/meeting-document-types`

Có public + public-options.

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-document-types/public` | Danh sách công khai. |
| GET | `/api/meeting-document-types/public-options` | Dropdown. |
| GET | `/api/meeting-document-types/stats` | Thống kê. |
| GET | `/api/meeting-document-types` | Danh sách phân trang. |
| GET | `/api/meeting-document-types/{id}` | Chi tiết. |
| POST | `/api/meeting-document-types` | Tạo. |
| PUT \| PATCH | `/api/meeting-document-types/{id}` | Cập nhật. |
| DELETE | `/api/meeting-document-types/{id}` | Xóa. |
| POST | `/api/meeting-document-types/bulk-delete` | Bulk xóa. |
| PATCH | `/api/meeting-document-types/bulk-status` | Bulk đổi trạng thái. |
| PATCH | `/api/meeting-document-types/{id}/status` | Đổi trạng thái. |
| GET | `/api/meeting-document-types/export` | Tải Excel `meeting-document-types.xlsx`. |
| POST | `/api/meeting-document-types/import` | Nhập Excel. |
| GET | `/api/meeting-document-types/import-template` | Tải file mẫu. |

Body và response giống mục 1.

---

## 4. Nhóm đại biểu — `/api/meeting-attendee-groups`

**Không có** endpoint public (admin-only).

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-attendee-groups/stats` | Thống kê. |
| GET | `/api/meeting-attendee-groups` | Danh sách phân trang. Query: `search`, `status`, `sort_by`, `sort_order`, `limit`. |
| GET | `/api/meeting-attendee-groups/{id}` | Chi tiết. |
| POST | `/api/meeting-attendee-groups` | Tạo. Body: [Catalog body](#catalog-body) (chỉ dùng `name`, `description`, `status`). |
| PUT \| PATCH | `/api/meeting-attendee-groups/{id}` | Cập nhật. |
| DELETE | `/api/meeting-attendee-groups/{id}` | Xóa. |
| POST | `/api/meeting-attendee-groups/bulk-delete` | Bulk xóa. |
| PATCH | `/api/meeting-attendee-groups/bulk-status` | Bulk đổi trạng thái. |
| PATCH | `/api/meeting-attendee-groups/{id}/status` | Đổi trạng thái. |
| GET | `/api/meeting-attendee-groups/export` | Tải Excel `meeting-attendee-groups.xlsx`. |
| POST | `/api/meeting-attendee-groups/import` | Nhập Excel. |
| GET | `/api/meeting-attendee-groups/import-template` | Tải file mẫu. |

Response: `CatalogResource`.

---

## 5. Đại biểu — `/api/meeting-attendees`

**Không có** endpoint public. Có thêm filter theo nhóm đại biểu.

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-attendees/stats` | `{ total, active, inactive }`. Query: `search`, `status`, `meeting_attendee_group_id`. |
| GET | `/api/meeting-attendees/user-options` | Dropdown chọn user khi tạo đại biểu (xem [User options](#user-options)). |
| GET | `/api/meeting-attendees` | Danh sách phân trang. Query: `search` (theo tên/email/đơn vị), `meeting_attendee_group_id`, `status`, `sort_by`, `sort_order`, `limit`. |
| GET | `/api/meeting-attendees/{id}` | Chi tiết. |
| POST | `/api/meeting-attendees` | Tạo. Body: [Attendee body](#attendee-body). |
| PUT \| PATCH | `/api/meeting-attendees/{id}` | Cập nhật. |
| DELETE | `/api/meeting-attendees/{id}` | Xóa. |
| POST | `/api/meeting-attendees/bulk-delete` | Bulk xóa. |
| PATCH | `/api/meeting-attendees/bulk-status` | Bulk đổi trạng thái. |
| PATCH | `/api/meeting-attendees/{id}/status` | Đổi trạng thái. |
| GET | `/api/meeting-attendees/export` | Tải Excel `meeting-attendees.xlsx`. Query: `search`, `meeting_attendee_group_id`, `status`. |
| POST | `/api/meeting-attendees/import` | Nhập Excel. Cột bắt buộc: `name`. |
| GET | `/api/meeting-attendees/import-template` | Tải file mẫu `import-meeting-attendees-template.xlsx`. |

### <a id="user-options"></a>5.1 User options dropdown (`GET /api/meeting-attendees/user-options`)

FE dùng cho ô chọn `user_id` khi tạo đại biểu.

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/meeting-attendees/user-options` |
| **Auth** | Bắt buộc + `X-Organization-Id`. |
| **Permission** | `meeting-attendees.store` (dùng chung quyền tạo đại biểu — **không cần** `users.index` để khỏi đụng CASL ở FE). |
| **Query** | `search` (tên/email), `limit` (mặc định 50). |
| **Response** | `{ "success": true, "data": [{ "id": 12, "name": "Nguyễn Văn A", "email": "a@example.com", "phone": "0901234567" }] }` |

Quy tắc lọc:

- Chỉ trả user `status=active` thuộc tổ chức hiện tại (có ít nhất một role trong `model_has_roles` của org).
- **Loại trừ** user đã được link với một `meeting_attendee` trong cùng org → tránh tạo đại biểu trùng.
- Sắp xếp `name asc`.

> Field `user_id` trong [Attendee body](#attendee-body) **không bắt buộc** — vẫn cho phép đại biểu ngoài hệ thống (không có account). Khi đó FE để trống `user_id` và nhập trực tiếp `name`/`email`/`phone`.

### 5.2 Response item (MeetingAttendeeResource)

```json
{
  "id": 1,
  "organization_id": 1,
  "meeting_attendee_group_id": 1,
  "group_name": "Tổ đại biểu số 1",
  "user_id": 12,
  "name": "Nguyễn Văn A",
  "position_name": "Phó chủ tịch",
  "department_name": "UBND phường",
  "email": "a@example.com",
  "phone": "0901234567",
  "status": "active",
  "note": "Đại biểu mời thường xuyên",
  "created_by": "Admin",
  "updated_by": "Admin",
  "created_at": "08:00:00 01/05/2026",
  "updated_at": "08:00:00 01/05/2026"
}
```

---

## Body chuẩn

### <a id="catalog-body"></a>Catalog body (Type / Location / Document Type / Attendee Group)

Dùng chung 1 FormRequest cho 4 danh mục:

| Field | Type | Required | Áp dụng | Ghi chú |
|---|---|---|---|---|
| `name` | string (≤255) | ✅ | Tất cả | Tên danh mục. |
| `description` | string (≤65535) | — | Tất cả | Mô tả. |
| `status` | enum | ✅ | Tất cả | `active` \| `inactive`. |
| `address` | string (≤255) | — | **Locations** | Địa chỉ. |
| `google_maps_url` | url (≤255) | — | **Locations** | Link Google Maps. |

Ví dụ tạo địa điểm:

```json
{
  "name": "Hội trường tầng 5",
  "description": "Hội trường UBND",
  "status": "active",
  "address": "Số 1 Trần Phú",
  "google_maps_url": "https://maps.google.com/?q=21.0278,105.8342"
}
```

Ví dụ tạo loại cuộc họp / loại tài liệu / nhóm đại biểu:

```json
{ "name": "Họp giao ban", "description": "Định kỳ tuần", "status": "active" }
```

### <a id="attendee-body"></a>Attendee body (`/api/meeting-attendees`)

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `name` | string (≤255) | ✅ | Họ tên đại biểu. |
| `meeting_attendee_group_id` | integer | — | FK `meeting_attendee_groups.id`. |
| `user_id` | integer | — | FK `users.id` — link tới tài khoản hệ thống (cho phép FCM/notification khi mời họp). |
| `position_name` | string (≤255) | — | Chức vụ. |
| `department_name` | string (≤255) | — | Đơn vị. |
| `email` | email (≤255) | — | Email. |
| `phone` | string (≤50) | — | Số điện thoại. |
| `status` | enum | ✅ | `active` \| `inactive`. |
| `note` | string | — | Ghi chú nội bộ. |

```json
{
  "name": "Nguyễn Văn A",
  "meeting_attendee_group_id": 1,
  "user_id": 12,
  "position_name": "Phó chủ tịch",
  "department_name": "UBND phường",
  "email": "a@example.com",
  "phone": "0901234567",
  "status": "active",
  "note": "Đại biểu mời thường xuyên"
}
```

---

## Patterns dùng chung

### Bulk delete
- **Method:** `POST /api/{resource}/bulk-delete`
- **Body:** `{ "ids": [1, 2, 3] }`
- **Response:** `{ "success": true, "message": "Xóa hàng loạt thành công!" }`

### Bulk update status
- **Method:** `PATCH /api/{resource}/bulk-status`
- **Body:** `{ "ids": [1, 2, 3], "status": "active" }`
- **Response:** `{ "success": true, "message": "Cập nhật trạng thái hàng loạt thành công!" }`

### Change status (single)
- **Method:** `PATCH /api/{resource}/{id}/status`
- **Body:** `{ "status": "inactive" }`
- **Response:** `{ "success": true, "message": "Đổi trạng thái thành công!", "data": <Resource> }`

### Filter chuẩn cho `index`

| Param | Type | Mô tả |
|---|---|---|
| `search` | string | Tìm theo tên (đại biểu thêm email/đơn vị). |
| `status` | string | `active` \| `inactive`. |
| `meeting_attendee_group_id` | integer | Chỉ áp dụng cho `/meeting-attendees`. |
| `sort_by` | string | `id` \| `name` \| `created_at` \| `updated_at`. |
| `sort_order` | string | `asc` \| `desc` (mặc định `asc` cho catalog, `desc` cho attendee). |
| `limit` | int | 1-100, mặc định 10. |
| `from_date` / `to_date` | date `Y-m-d` | Lọc theo `created_at`. |

### <a id="import"></a>Export / Import Excel

Mọi catalog đều hỗ trợ export + import (cùng format header, cùng pattern body).

**Export** (`GET /api/{resource}/export`)

- Auth bắt buộc, permission `{resource}.export`.
- Query giống `index` (`search`, `status`, `meeting_attendee_group_id` cho attendees…).
- Response: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` — file Excel tải xuống trực tiếp.
- Cột xuất chung cho 4 catalog: `STT, Tên, Mô tả, Địa chỉ, Google Maps URL, Trạng thái, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID` — 2 cột địa chỉ/Google Maps chỉ có dữ liệu với `meeting-locations`.
- Cột xuất cho `meeting-attendees`: `STT, Họ tên, Nhóm đại biểu, Chức vụ, Đơn vị, Email, Số điện thoại, Trạng thái, Ghi chú, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID`.

**Import** (`POST /api/{resource}/import`)

- Auth bắt buộc, permission `{resource}.import`.
- `Content-Type: multipart/form-data`, field `file`: xlsx | xls | csv, ≤ 10240 KB.
- Cột nhận theo header (tiếng Việt khớp file export hoặc dùng `name`/`status`/… key snake_case).

**Import template** (`GET /api/{resource}/import-template`)

- Auth bắt buộc, **dùng chung permission `{resource}.import`** (không có quyền riêng).
- Trả về file Excel rỗng chỉ có header (theo nhãn tiếng Việt) — FE dùng nút "Tải mẫu" trên màn import.
- Header của template (cột nghiệp vụ — `status` lấy default `active`):
  - Type / DocType / AttendeeGroup: `Tên, Mô tả`.
  - Locations: `Tên, Mô tả, Địa chỉ, Google Maps URL`.
  - Attendees: `Họ tên, Chức vụ, Đơn vị, Email, Số điện thoại, Ghi chú`.

| Catalog | Cột bắt buộc | Cột không bắt buộc (default) |
|---|---|---|
| `meeting-types` | `name` | `description`, `status` (default `active`) |
| `meeting-locations` | `name` | `description`, `address`, `google_maps_url`, `status` (default `active`) |
| `meeting-document-types` | `name` | `description`, `status` (default `active`) |
| `meeting-attendee-groups` | `name` | `description`, `status` (default `active`) |
| `meeting-attendees` | `name` | `position_name`, `department_name`, `email`, `phone`, `note`, `status` (default `active`) |

- Response thành công: `{ "success": true, "message": "Nhập ... thành công!" }`.
- Validate row-level skip (tiếp tục import các dòng hợp lệ); `name.required`, `status.in:active,inactive`, `email.email`.

### Validation lỗi (422)

```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "name": ["Tên là trường bắt buộc."],
    "status": ["Trạng thái không hợp lệ."]
  }
}
```

### Quyền (403) / Cross-org (404)

- Thiếu permission: `403 FORBIDDEN`.
- Truy cập bản ghi của tổ chức khác qua route param `{id}`: trả `404 NOT_FOUND` (middleware `ensure.route.org` chặn).

---

## Tóm tắt cho FE

1. **3 màn dropdown chia sẻ chung 1 component** dùng `*/public-options` (loại cuộc họp / địa điểm / loại tài liệu) — không cần auth, dữ liệu tối giản (`{id, name, description}`).
2. **5 trang quản trị** (cùng pattern CRUD + bulk + status):
   - Loại cuộc họp / Địa điểm / Loại tài liệu / Nhóm đại biểu → dùng `CatalogResource` + `StoreCatalogRequest`.
   - Đại biểu → resource riêng (`MeetingAttendeeResource`), thêm filter theo nhóm.
3. **Form địa điểm** mở rộng thêm 2 trường địa chỉ (`address`, `google_maps_url`).
4. **Form đại biểu** không dùng địa lý, có thêm `meeting_attendee_group_id`, `user_id`, `position_name`, `department_name`, `email`, `phone`, `note`.
5. Mọi response list theo cấu trúc `data.items` + `data.pagination` (envelope của `MeetingCollection`/`CatalogCollection`).
