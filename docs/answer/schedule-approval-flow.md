# Lịch công tác — Tách status & Approval flow

## Mô hình 2 trạng thái độc lập

| Cột | Ý nghĩa | Giá trị |
|---|---|---|
| `status` | Trạng thái ban hành | `0` = DRAFT (bản nháp), `1` = PUBLISHED (đã ban hành) |
| `approval_status` | Trạng thái duyệt | `null` = chưa gửi duyệt, `"pending"` = đợi duyệt, `"approved"` = đã duyệt, `"rejected"` = từ chối |

Việc có cần duyệt hay không phụ thuộc vào `org_scheduling_settings`:
- `executive_requires_approval` (boolean) — cho phân hệ EXECUTIVE
- `office_requires_approval` (boolean) — cho phân hệ OFFICE

---

## Flow theo cờ `*_requires_approval`

### Khi `requires_approval = true` (tổ chức bật duyệt)

```
Tạo mới       → status=DRAFT, approval_status=null
Ban hành      → status=PUBLISHED, approval_status=PENDING   (Observers fire SchedulePublished)
Duyệt         → approval_status=APPROVED  (status vẫn PUBLISHED)
Từ chối       → approval_status=REJECTED (status vẫn PUBLISHED)
Hủy ban hành  → status=DRAFT
Cập nhật ND   → không đổi status/approval_status (Observers fire ScheduleUpdated nếu đã PUBLISHED + field quan trọng thay đổi)
```

### Khi `requires_approval = false` (tổ chức không duyệt)

```
Tạo mới       → status=DRAFT, approval_status=null
Ban hành      → status=PUBLISHED, approval_status=APPROVED  (tự động set)
Hủy ban hành  → status=DRAFT
Cập nhật ND   → không đổi status/approval_status
```

---

## API endpoints

### 1. Tạo mới — `POST /api/schedules`

Service luôn ghi đè `status=DRAFT`, `approval_status=null`. FE không cần gửi `status`.

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

Cập nhật nội dung. Khi đã PUBLISHED, nếu field quan trọng (content, date_time, location) thay đổi → Observer tự fire `ScheduleUpdated`.

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

### 3. Ban hành / Hủy ban hành — `PATCH /api/schedules/{id}/status`

Đổi trạng thái DRAFT ↔ PUBLISHED. **Tự động set `approval_status` dựa trên flag**:

- `requires_approval=true` → `approval_status = "pending"` (tự động gửi duyệt)
- `requires_approval=false` → `approval_status = "approved"` (tự động duyệt)

> Permission: `schedules-{executive|office}.changeStatus`

```json
// Request — Ban hành
{ "status": "PUBLISHED" }

// Response 200
{
  "success": true,
  "message": "Cập nhật trạng thái thành công!",
  "data": {
    "id": 42,
    "status": 1,
    "approval_status": "pending",   // hoặc "approved" nếu requires_approval=false
    ...
  }
}

// Request — Hủy ban hành
{ "status": "DRAFT" }
```

### 4. Duyệt lịch — `PATCH /api/schedules/{id}/approve`

Set `approval_status = "approved"` + ghi `approved_by`/`approved_at`.

> Permission: `schedules-{executive|office}.approve`

```json
// Response 200
{
  "success": true,
  "message": "Duyệt lịch công tác thành công!",
  "data": {
    "id": 42,
    "status": 1,
    "approval_status": "approved",
    "approved_by": 5,
    "approved_at": "2026-06-05T10:30:00+07:00",
    "approver": { "id": 5, "name": "Nguyễn Văn A", ... }
  }
}
```

### 5. Từ chối — `PATCH /api/schedules/{id}/reject`

Set `approval_status = "rejected"`.

> Permission: `schedules-{executive|office}.approve`

```json
// Request
{ "rejection_note": "Thiếu nội dung chi tiết" }

// Response 200
{
  "success": true,
  "message": "Từ chối lịch công tác thành công!",
  "data": {
    "id": 42,
    "status": 1,
    "approval_status": "rejected",
    ...
  }
}
```

### 6. Bulk cập nhật trạng thái — `PATCH /api/schedules/bulk-status`

> Permission: `schedules-{executive|office}.update`

```json
// Request
{ "ids": [1, 2, 3], "status": "PUBLISHED" }
```

**Lưu ý:** Bulk chỉ set `status`, không set `approval_status`. Nếu muốn duyệt từng bản ghi thì dùng API riêng.

### 7. Thống kê — `GET /api/schedules/stats`

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
| `draft` | status = 0 (DRAFT) |
| `pending_approval` | status = 1 (PUBLISHED) AND approval_status = 'pending' |
| `approved` | status = 1 (PUBLISHED) AND approval_status = 'approved' |
| `rejected` | status = 1 (PUBLISHED) AND approval_status = 'rejected' |
| `published` | status = 1 (PUBLISHED) — tổng PUBLISHED = pending + approved + rejected |

---

## UI logic gợi ý

### Badge hiển thị

| `status` | `approval_status` | Hiển thị |
|---|---|---|
| 0 (DRAFT) | null | `Nháp` |
| 1 (PUBLISHED) | pending | `Đã ban hành` + `Chờ duyệt` |
| 1 (PUBLISHED) | approved | `Đã ban hành` + `Đã duyệt` |
| 1 (PUBLISHED) | rejected | `Đã ban hành` + `Không duyệt` |

### Nút hành động

| Trạng thái hiện tại | Nút hiển thị |
|---|---|
| DRAFT | `Ban hành` (PATCH /{id}/status, body status=PUBLISHED) |
| PUBLISHED, approval_status=pending | `Duyệt` (PATCH /{id}/approve), `Từ chối` (PATCH /{id}/reject) |
| PUBLISHED, approval_status=approved | Không có nút duyệt. Có `Hủy ban hành` |
| PUBLISHED, approval_status=rejected | Không có nút duyệt. Có `Hủy ban hành` |

### Nếu tổ chức `requires_approval = false`

Khi publish → tự động `approval_status = "approved"`, không cần bước duyệt. FE có thể check các flag từ `GET /api/scheduling-settings`:
- `executive_requires_approval` (bool)
- `office_requires_approval` (bool)

---

## Upload & đổi tên attachment

### Response format

```json
{
  "attachments": [
    {
      "id": 10,
      "media_id": null,
      "name": "Tài liệu họp",
      "file_name": "tailieu_hop.pdf",
      "url": "/storage/schedules/2026/06/uuid.pdf",
      "mime_type": "application/pdf",
      "size": 204800,
      "sort_order": 0
    }
  ]
}
```

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

### Xoá attachment

Gửi `remove_media_ids` array:

```json
{ "remove_media_ids": [10, 11] }
```

---

## Bộ lọc (query params) cho `GET /api/schedules`

| Param | Kiểu | Mô tả | Ví dụ |
|---|---|---|---|
| `module_type` | string | Phân hệ (`EXECUTIVE`, `OFFICE`) | `EXECUTIVE` |
| `search` | string | Tìm trong content, location, preparation_unit, departments_text | `họp giao ban` |
| `status` | int | Trạng thái ban hành (`0`=DRAFT, `1`=PUBLISHED) | `1` |
| `approval_status` | string | Trạng thái duyệt (`pending`, `approved`, `rejected`) | `pending` |
| `session` | string | Buổi (`S`=Sáng, `C`=Chiều, `T`=Tối) | `S` |
| `host_id` | int | ID người chủ trì | `2` |
| `driver_id` | int | ID lái xe | `5` |
| `date_time` | date | Lọc chính xác ngày (Y-m-d) | `2026-06-01` |
| `from_date` | date | Lọc từ ngày (Y-m-d) | `2026-06-01` |
| `to_date` | date | Lọc đến ngày (Y-m-d) | `2026-06-07` |
| `week_number` | int | Số tuần trong năm | `23` |
| `year` | int | Năm | `2026` |
| `view_mode` | string | `personal` = lịch của tôi, `managed` = lịch tôi chủ trì | `personal` |
| `sort_by` | string | `id`, `date_time`, `session`, `sort_order`, `status`, `created_at`, `updated_at` | `date_time` |
| `sort_order` | string | `asc` / `desc` | `asc` |
| `limit` | int | Số bản ghi mỗi trang | `20` |

---

## Endpoint đặc biệt

### General (cho nhân viên xem lịch chung)

| Endpoint | Permission |
|---|---|
| `GET /api/schedules/general` | Chỉ cần auth |
| `GET /api/schedules/general/weekly-matrix` | Chỉ cần auth |
| `GET /api/schedules/general/weeks` | Chỉ cần auth |

### Driver (cho lái xe)

| Endpoint | Permission |
|---|---|
| `GET /api/schedules/driver-view` | Policy `driverViewAny` |
| `GET /api/schedules/driver-view/{id}` | Policy `driverView` |

### Export

| Endpoint | Permission |
|---|---|
| `GET /api/schedules/export` | `schedules-{executive\|office}.export` (xlsx) |
| `GET /api/schedules/export-pdf` | `schedules-{executive\|office}.export` (PDF) |
| `GET /api/schedules/export-word` | `schedules-{executive\|office}.export` (docx) |

### Khác

| Endpoint | Method | Permission |
|---|---|---|
| `/api/schedules/reorder` | PATCH | `schedules-{executive\|office}.update` |
| `/api/schedules/{id}/duplicate` | POST | `schedules-{executive\|office}.store` |
| `/api/schedules/bulk-delete` | DELETE | `schedules-{executive\|office}.destroy` |
| `/api/schedules/weeks` | GET | `schedules-{executive\|office}.index` |
