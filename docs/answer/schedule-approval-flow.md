# Lịch công tác — Tách status & Approval flow

## Mô hình 2 trạng thái độc lập

Trước đây `status` gộp chung: `0=DRAFT, 1=PENDING, 2=PUBLISHED, 3=CANCELLED`.

Nay tách thành 2 cột:

| Cột | Ý nghĩa | Giá trị |
|---|---|---|
| `status` | Trạng thái ban hành | `0` = DRAFT (bản nháp), `1` = PUBLISHED (đã ban hành) |
| `approval_status` | Trạng thái duyệt | `null` = chưa gửi duyệt, `"pending"` = đợi duyệt, `"approved"` = đã duyệt, `"rejected"` = từ chối |

Việc có cần duyệt hay không phụ thuộc vào `org_scheduling_settings.requires_approval` (boolean, mỗi tổ chức một giá trị).

---

## Flow theo cờ `requires_approval`

### Khi `requires_approval = true` (tổ chức bật duyệt)

```
Tạo mới       → status=0 (DRAFT), approval_status=null
Gửi duyệt     → approval_status="pending" (status vẫn DRAFT)
Duyệt         → approval_status="approved" (status vẫn DRAFT)
Từ chối       → approval_status="rejected" (status vẫn DRAFT)
Ban hành      → status=1 (PUBLISHED) (phải có approval_status="approved" trước, nếu chưa → lỗi 422)
Hủy ban hành  → status=0 (DRAFT)
Cập nhật ND   → không đổi status/approval_status
```

### Khi `requires_approval = false` (tổ chức không duyệt)

```
Tạo mới       → status=0 (DRAFT), approval_status=null
Ban hành      → status=1 (PUBLISHED), approval_status="approved" (tự động set)
Hủy ban hành  → status=0 (DRAFT)
Cập nhật ND   → không đổi status/approval_status
```

---

## Response có thêm trường `approval_status`

Mọi response chứa schedule nay có thêm:

```json
{
  "status": 0,
  "approval_status": null,
  "approved_by": null,
  "approved_at": null,
  "approver": null
}
```

`approval_status` có thể là: `null`, `"pending"`, `"approved"`, `"rejected"`.

---

## API endpoints

### 1. Tạo mới — `POST /api/schedules`

Service **luôn ghi đè status=DRAFT** và để `approval_status=null`. FE không cần gửi `status`.

```json
// Request (multipart/form-data)
{
  "module_type": "EXECUTIVE",
  "content": "Họp giao ban",
  "date_time": "2026-06-10T08:00:00",
  "session": "S",
  "location": "Phòng họp 1",
  "host_id": 2
}

// Response 201
{
  "success": true,
  "message": "Tạo lịch công tác thành công!",
  "data": {
    "id": 42,
    "status": 0,
    "approval_status": null,
    ...
  }
}
```

### 2. Cập nhật — `PUT|PATCH /api/schedules/{id}`

Cập nhật nội dung, không đổi `status`/`approval_status`. Hỗ trợ cập nhật `attachments[].name` để đổi tên hiển thị.

```json
// Request
{
  "content": "Họp giao ban (đã sửa)",
  "location": "Phòng họp 2",
  "attachments": [
    { "id": 10, "name": "Tên mới" },
    { "id": 11 }
  ]
}
```

### 3. Gửi duyệt — `PATCH /api/schedules/{id}/submit-for-approval`

Set `approval_status` từ `null` → `"pending"`.

> Permission: `schedules.update`. Chỉ gọi được khi `approval_status` đang là `null`. Nếu đã gửi duyệt rồi → lỗi 422.

```json
// Response 200
{
  "success": true,
  "message": "Gửi duyệt lịch công tác thành công!",
  "data": {
    "id": 42,
    "status": 0,
    "approval_status": "pending",
    ...
  }
}
```

### 4. Duyệt lịch — `PATCH /api/schedules/{id}/approve`

Set `approval_status="approved"` + ghi `approved_by`/`approved_at`.

> Permission: `schedules.approve`.

```json
// Response 200
{
  "success": true,
  "message": "Duyệt lịch công tác thành công!",
  "data": {
    "id": 42,
    "status": 0,
    "approval_status": "approved",
    "approved_by": 5,
    "approved_at": "2026-06-05T10:30:00+07:00",
    "approver": { "id": 5, "name": "Nguyễn Văn A", ... }
  }
}
```

### 5. Từ chối — `PATCH /api/schedules/{id}/reject`

Set `approval_status="rejected"`. Nhận `rejection_note` (hiện chỉ log, không lưu DB).

> Permission: `schedules.approve`.

```json
// Request
{
  "rejection_note": "Thiếu nội dung chi tiết"
}

// Response 200
{
  "success": true,
  "message": "Từ chối lịch công tác thành công!",
  "data": {
    "id": 42,
    "status": 0,
    "approval_status": "rejected",
    ...
  }
}
```

### 6. Ban hành / Hủy ban hành — `PATCH /api/schedules/{id}/status`

Đổi trạng thái DRAFT ↔ PUBLISHED.

- DRAFT → PUBLISHED: nếu `requires_approval=true` thì bắt buộc `approval_status=approved`, nếu chưa → lỗi 422. Nếu `requires_approval=false` thì tự động set `approval_status=approved`.
- PUBLISHED → DRAFT: cho phép (rút lại).

> Permission: `schedules.changeStatus`.

```json
// Request
{
  "status": "PUBLISHED"
}

// Response 200
{
  "success": true,
  "message": "Cập nhật trạng thái thành công!",
  "data": {
    "id": 42,
    "status": 1,
    "approval_status": "approved",
    ...
  }
}

// Trường hợp chưa duyệt mà bấm ban hành → 422
{
  "success": false,
  "message": "Lịch công tác chưa được duyệt, không thể ban hành."
}
```

### 7. Bulk cập nhật trạng thái — `PATCH /api/schedules/bulk-status`

> Permission: `schedules.update`.

```json
// Request
{
  "ids": [1, 2, 3],
  "status": "PUBLISHED"
}
```

### 8. Thống kê — `GET /api/schedules/stats`

> Permission: `schedules.stats`. Hỗ trợ đầy đủ filter giống index.

```json
// Response
{
  "success": true,
  "data": {
    "total": 22,
    "draft": 5,
    "pending_approval": 3,
    "approved": 4,
    "rejected": 1,
    "published": 10
  }
}
```

| Key | Điều kiện SQL |
|---|---|
| `draft` | status=0 AND approval_status IS NULL |
| `pending_approval` | status=0 AND approval_status='pending' |
| `approved` | status=0 AND approval_status='approved' |
| `rejected` | status=0 AND approval_status='rejected' |
| `published` | status=1 |

---

## Bộ lọc (query params) cho `GET /api/schedules`

Tất cả endpoint list (`index`, `stats`, `weeklyMatrix`, `weeks`, `general*`, `export*`) đều dùng chung `scopeFilter` trong Schedule model, hỗ trợ các query param sau:

### Filter chung (từ FilterRequest)
| Param | Kiểu | Mô tả | Ví dụ |
|---|---|---|---|
| `search` | string | Tìm trong content, location, preparation_unit, departments_text | `họp giao ban` |
| `from_date` | date | Lọc từ ngày (Y-m-d), áp dụng trên cột `date_time` | `2026-06-01` |
| `to_date` | date | Lọc đến ngày (Y-m-d), áp dụng trên cột `date_time` | `2026-06-07` |
| `sort_by` | string | Trường sắp xếp | `date_time` |
| `sort_order` | string | `asc` hoặc `desc` | `asc` |
| `limit` | integer | Số bản ghi mỗi trang. `-1` = không phân trang (tự động map sang 1000000) | `20` |

### Filter riêng của Scheduling
| Param | Kiểu | Mô tả | Ví dụ |
|---|---|---|---|
| `module_type` | string | Phân hệ (`EXECUTIVE`, `OFFICE`) | `EXECUTIVE` |
| `status` | int | Trạng thái ban hành (`0`=DRAFT, `1`=PUBLISHED) | `1` |
| `approval_status` | string | Trạng thái duyệt (`pending`, `approved`, `rejected`) | `approved` |
| `session` | string | Buổi (`S`=Sáng, `C`=Chiều, `T`=Tối) | `S` |
| `host_id` | integer | ID người chủ trì | `2` |
| `driver_id` | integer | ID lái xe | `5` |
| `date_time` | date | Lọc chính xác theo ngày (Y-m-d) | `2026-06-01` |
| `date` | date | Alias của `date_time` | `2026-06-01` |
| `week` | string | Lọc theo tuần (định dạng `YYYY-Www`) | `2026-W23` |
| `year` | integer | Lọc theo năm | `2026` |
| `week_number` | integer | Lọc theo số tuần trong năm | `23` |
| `view_mode` | string | `personal` = lịch của tôi (tôi tạo / chủ trì / lái xe / người nhận), `managed` = lịch tôi chủ trì | `personal` |

### Sort cho phép
`id`, `date_time`, `session`, `sort_order`, `status`, `created_at`, `updated_at`

### Ghi chú
- `date` và `date_time` là alias — dùng chung 1 logic `whereDate()`
- `week` format `YYYY-Www` sẽ tự parse ra `year` + `week_number` và lọc riêng
- `view_mode=personal` scope theo `created_by OR host_id OR driver_id OR recipients.user_id = auth()->id()`
- `view_mode=managed` scope theo `host_id = auth()->id()`
- Lái xe (role `scheduling-lai-xe`) tự động bị filter `driver_id = auth()->id()` trong `index()` và `weekMatrix()`

### Ví dụ kết hợp
```
GET /api/schedules?module_type=EXECUTIVE&status=0&approval_status=pending&from_date=2026-06-01&to_date=2026-06-07&sort_by=date_time&sort_order=asc&limit=20
GET /api/schedules?week=2026-W23&approval_status=approved
GET /api/schedules?view_mode=personal&year=2026&week_number=23
```

---

## Endpoint đặc biệt

### General (cho nhân viên xem lịch chung)
Các endpoint `general*` tự động thêm `general_visibility=true` vào filter:

| Endpoint | Permission |
|---|---|
| `GET /api/schedules/general` | Không cần permission (chỉ auth) |
| `GET /api/schedules/general/weekly-matrix` | Không cần permission (chỉ auth) |
| `GET /api/schedules/general/weeks` | Không cần permission (chỉ auth) |

`general_visibility=true` scope: `status=PUBLISHED OR created_by=auth()->id()` (nhân viên xem được lịch đã ban hành + lịch nháp của chính mình).

### Driver (cho lái xe)
| Endpoint | Permission |
|---|---|
| `GET /api/schedules/driver-view` | Policy: `driverViewAny` |
| `GET /api/schedules/driver-view/{id}` | Policy: `driverView` |

Chỉ xem được lịch PUBLISHED được gán cho mình.

### Export
| Endpoint | Permission | Định dạng |
|---|---|---|
| `GET /api/schedules/export` | `schedules.export` | Excel (.xlsx) |
| `GET /api/schedules/export-pdf` | `schedules.export` | PDF |
| `GET /api/schedules/export-word` | `schedules.export` | Word (.docx) |

### Khác
| Endpoint | Method | Permission |
|---|---|---|
| `/api/schedules/reorder` | PATCH | `schedules.update` |
| `/api/schedules/{id}/duplicate` | POST | `schedules.store` |
| `/api/schedules/bulk-delete` | DELETE | `schedules.destroy` |
| `/api/schedules/weeks` | GET | `schedules.index` |

---

## UI logic gợi ý

### Màn hình danh sách

Thay vì 1 badge status như cũ, hiển thị 2 badge độc lập:

| `status` | `approval_status` | Hiển thị |
|---|---|---|
| 0 | null | `Nháp` |
| 0 | pending | `Nháp` + `Chờ duyệt` |
| 0 | approved | `Nháp` + `Đã duyệt` |
| 0 | rejected | `Nháp` + `Không duyệt` |
| 1 | * | `Đã ban hành` |

### Nút hành động

| Trạng thái hiện tại | Nút hiển thị |
|---|---|
| DRAFT, approval_status=null | `Gửi duyệt` (PATCH /{id}/submit-for-approval). Nếu `requires_approval=false` thì hiện thẳng `Ban hành` |
| DRAFT, approval_status=pending | `Duyệt` (PATCH /{id}/approve), `Từ chối` (PATCH /{id}/reject) |
| DRAFT, approval_status=approved | `Ban hành` (PATCH /{id}/status với status=PUBLISHED) |
| DRAFT, approval_status=rejected | Không có nút đặc biệt (có thể sửa nội dung rồi gửi duyệt lại nếu cần) |
| PUBLISHED | `Hủy ban hành` (PATCH /{id}/status với status=DRAFT) |

---

## Upload & đổi tên attachment

### Response format

```json
{
  "attachments": [
    {
      "id": 10,
      "name": "Tài liệu họp",
      "file_name": "tailieu_hop.pdf",
      "url": "/storage/schedules/2026/06/uuid.pdf",
      "mime_type": "application/pdf",
      "size": 204800
    }
  ]
}
```

| Field | Mô tả |
|---|---|
| `name` | Tên hiển thị (do FE đặt hoặc mặc định = tên file không đuôi) |
| `file_name` | Tên file gốc khi upload |
| `url` | Link download |

### Cách đặt tên khi upload file mới

Gửi kèm `attachment_names` (array, index khớp với thứ tự file trong `files[]`):

```
POST /api/schedules
Content-Type: multipart/form-data

files[0]: (binary) tailieu_hop.pdf
files[1]: (binary) bao_cao.docx
attachment_names[0]: "Tài liệu họp tháng 6"
attachment_names[1]: "Báo cáo tuần"
```

Nếu không gửi `attachment_names`, `name` sẽ mặc định là tên file bỏ đuôi mở rộng (ví dụ `tailieu_hop`).

### Cách đổi tên attachment đã có

Khi update, gửi `name` mới trong `attachments` array:

```json
// PATCH /api/schedules/42
{
  "attachments": [
    { "id": 10, "name": "Tài liệu đã sửa tên" },
    { "id": 11 }
  ]
}
```

- Có `id` + `name` → cập nhật `name` cho attachment đó
- Có `id` nhưng không có `name` → giữ nguyên
- Không có trong list → bị xoá

### Xoá attachment

Gửi `remove_media_ids` array chứa các ID cần xoá:

```json
// PATCH /api/schedules/42
{
  "remove_media_ids": [10, 11]
}
```
