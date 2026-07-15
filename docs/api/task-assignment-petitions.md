# API: Đơn thư (task-assignment-petitions)

> Cập nhật lần cuối: 15/07/2026 — bổ sung 2 endpoint chưa được doc: `available-departments`, `unlock`.

Status: **Đã implement**

Base URL: `/api/task-assignment-petitions`
Auth: Bearer token + `X-Organization-Id` header
Permission prefix: `task-assignment-petitions.{action}`

---

## 1. Danh sách đơn thư

```
GET /api/task-assignment-petitions
```

Permission: `task-assignment-petitions.index`

### Query Params

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `search` | string | No | Tìm kiếm theo tên, CCCD, SĐT, email, nội dung đơn |
| `processing_status` | string | No | `new`, `processing`, `completed`, `paused`, `cancelled` |
| `department_id` | int | No | ID phòng ban |
| `submission_date_from` | date | No | Ngày gửi đơn từ (YYYY-MM-DD) |
| `submission_date_to` | date | No | Ngày gửi đơn đến (YYYY-MM-DD) |
| `deadline_date_from` | date | No | Hạn xử lý từ (YYYY-MM-DD) |
| `deadline_date_to` | date | No | Hạn xử lý đến (YYYY-MM-DD) |
| `sort_by` | string | No | `id`, `submission_date`, `deadline_date`, `created_at`, `updated_at` |
| `sort_order` | string | No | `asc` / `desc` (default: desc) |
| `limit` | int | No | Số bản ghi/trang (default: 20) |

### Department scoping

- Admin/Super Admin/Quản trị: thấy tất cả đơn của mọi phòng ban
- User thường: chỉ thấy đơn của phòng ban mình (từ `taskAssignmentUser->task_assignment_department_id`)

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "department_id": 1,
      "department": {
        "id": 1,
        "name": "Văn phòng"
      },
      "submission_date": "10/06/2026",
      "deadline_date": "15/06/2026",
      "sender_name": "Nguyễn Văn A",
      "sender_address": "Số 1, đường A, TP.HCM",
      "sender_cccd": "079202001234",
      "sender_phone": "0901234567",
      "sender_email": "nguyenvana@example.com",
      "content": "Nội dung đơn chi tiết...",
      "processing_status": "new",
      "timing_status": "upcoming",
      "is_overdue": false,
      "completed_at": null,
      "document_number": null,
      "document_excerpt": null,
      "response_content": null,
      "attachments": [],
      "created_by": { "id": 1, "name": "Admin" },
      "updated_by": null,
      "created_at": "10:00:00 10/06/2026",
      "updated_at": "10:00:00 10/06/2026"
    }
  ],
  "meta": {
    "pagination": { ... }
  }
}
```

### Status field

| `processing_status` | Label |
|---|---|
| `new` | Mới tiếp nhận |
| `processing` | Đang xử lý |
| `completed` | Đã hoàn thành |
| `paused` | Tạm dừng |
| `cancelled` | Đã hủy |

### Timing field

| `timing_status` | Ý nghĩa |
|---|---|
| `upcoming` | Chưa đến hạn |
| `early` | Đã hoàn thành, sớm hơn hạn |
| `on_time` | Đã hoàn thành đúng hạn |
| `late` | Đã hoàn thành trễ hạn |
| `overdue` | Chưa hoàn thành, đã quá hạn |
| `cancelled` | Đã hủy |

`is_overdue`: boolean flag, true khi chưa done/cancelled + đã quá `deadline_date`.

---

## 1b. Danh sách phòng ban khả dụng (để lọc/tạo đơn)

```
GET /api/task-assignment-petitions/available-departments
```

Auth: đăng nhập (không yêu cầu permission riêng ngoài `auth:sanctum`).

Trả về danh sách phòng ban mà user hiện tại được phép thao tác đơn thư (dựa theo `taskAssignmentUsers` đang active của user). Dùng cho dropdown lọc/tạo đơn ở FE.

---

## 2. Thống kê

```
GET /api/task-assignment-petitions/stats
```

Permission: `task-assignment-petitions.index`

### Query Params

Giống bộ lọc của index (có department scoping).

### Response

```json
{
  "success": true,
  "data": {
    "total": 25,
    "new": 5,
    "processing": 10,
    "completed": 7,
    "paused": 2,
    "cancelled": 1
  }
}
```

---

## 3. Chi tiết đơn thư

```
GET /api/task-assignment-petitions/{petition}
```

Permission: `task-assignment-petitions.show`

Response format giống 1 item trong index, có thêm attachments đầy đủ (url, original_name, mime_type, size).

---

## 4. Tạo đơn thư

```
POST /api/task-assignment-petitions
```

Permission: `task-assignment-petitions.store`

Content-Type: `multipart/form-data`

### Body Params

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `department_id` | int | **Yes** | ID phòng ban tiếp nhận |
| `submission_date` | date | **Yes** | Ngày gửi đơn (YYYY-MM-DD) |
| `deadline_date` | date | No | Hạn xử lý (YYYY-MM-DD), phải >= submission_date |
| `sender_name` | string | **Yes** | Người gửi đơn (max 255) |
| `sender_address` | string | No | Địa chỉ (max 500) |
| `sender_cccd` | string | No | CCCD (max 20) |
| `sender_phone` | string | No | SĐT (max 30) |
| `sender_email` | email | No | Email |
| `content` | string | No | Nội dung đơn |
| `attachments[]` | file | No | File đính kèm (max 10, ≤20MB, pdf/doc/docx/xls/xlsx/ppt/pptx/jpg/png/gif) |

### Response

```json
{
  "success": true,
  "message": "Tạo đơn thư thành công!",
  "data": { ... }
}
```

---

## 5. Cập nhật đơn thư

```
PUT /api/task-assignment-petitions/{petition}
```

Permission: `task-assignment-petitions.update`

Content-Type: `multipart/form-data`

### Body Params

Tất cả field của store (không required). Thêm:

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `attachments[]` | file | No | File đính kèm mới |
| `remove_attachment_ids[]` | int[] | No | ID attachment cần xóa |
| `processing_status` | string | No | Trạng thái mới. Nếu = `completed` → tự set `completed_at = now()` |
| `completed_at` | datetime | No | Ngày hoàn thành (nếu không gửi và status=completed → auto now()) |
| `document_number` | string | No | Số ký hiệu văn bản trả lời |
| `document_excerpt` | string | No | Trích yếu văn bản |
| `response_content` | string | No | Tóm tắt nội dung trả lời |

- Field không gửi → giữ nguyên giá trị cũ
- `attachments` + `remove_attachment_ids` dùng để sync file

### Response

```json
{
  "success": true,
  "message": "Cập nhật đơn thư thành công!",
  "data": { ... }
}
```

---

## 6. Đổi trạng thái

```
PATCH /api/task-assignment-petitions/{petition}/status
```

Permission: `task-assignment-petitions.changeStatus`

### Body Params

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `processing_status` | string | **Yes** | `new`, `processing`, `completed`, `paused`, `cancelled` |

- Khi chuyển sang `completed` → tự set `completed_at = now()`
- Khi chuyển từ `completed` sang trạng thái khác → xóa `completed_at`

### Response

```json
{
  "success": true,
  "message": "Cập nhật trạng thái thành công!",
  "data": { ... }
}
```

---

## 6b. Mở khóa đơn thư (unlock)

```
PATCH /api/task-assignment-petitions/{petition}/unlock
```

Permission: `task-assignment-petitions.manage`, và user phải thuộc phòng ban "tổng hợp đơn thư" (`is_petition_overview = true`) — người tạo đơn không tự mở khóa được, kể cả có quyền `update`.

Chuyển đơn thư về trạng thái `processing` (dùng lại logic của endpoint đổi trạng thái) — dùng khi cần mở lại đơn đã `completed`/`cancelled` để xử lý tiếp.

### Response

```json
{
  "success": true,
  "message": "Đã mở khóa đơn thư thành công!",
  "data": { ... }
}
```

---

## 7. Cập nhật tiến độ xử lý

```
PATCH /api/task-assignment-petitions/{petition}/progress
```

Permission: `task-assignment-petitions.update`

Content-Type: `multipart/form-data`

### Body Params

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `completed_at` | datetime | No | Ngày hoàn thành xử lý |
| `document_number` | string | No | Số ký hiệu văn bản trả lời (max 255) |
| `document_excerpt` | string | No | Trích yếu văn bản (max 2000) |
| `response_content` | string | No | Tóm tắt nội dung trả lời |
| `attachments[]` | file | No | File đính kèm trả lời (max 10, ≤20MB) |
| `remove_attachment_ids[]` | int[] | No | DS ID attachment cần xóa |

File upload qua endpoint này có `type: "progress"`, phân biệt với `type: "petition"` ở store/update.

### Response

```json
{
  "success": true,
  "message": "Cập nhật tiến độ thành công!",
  "data": { ... }
}
```

---

## 8. Xóa đơn thư

```
DELETE /api/task-assignment-petitions/{petition}
```

Permission: `task-assignment-petitions.destroy`

### Response

```json
{
  "success": true,
  "message": "Đã xóa đơn thư!"
}
```

---

## 9. Xóa hàng loạt

```
DELETE /api/task-assignment-petitions/bulk-delete
```

Permission: `task-assignment-petitions.bulkDestroy`

### Body Params

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `ids` | int[] | **Yes** | Danh sách ID cần xóa |

### Response

```json
{
  "success": true,
  "message": "Đã xóa thành công 3 đơn thư!"
}
```

---

## 10. Xuất Excel

```
GET /api/task-assignment-petitions/export
```

Permission: `task-assignment-petitions.export`

Áp dụng cùng bộ lọc với index. Trả về file Excel `.xlsx`.

Các cột: STT, Người gửi đơn, CCCD, Số điện thoại, Email, Địa chỉ, Nội dung đơn, Ngày gửi đơn, Hạn xử lý, Phòng ban, Trạng thái, Ngày hoàn thành, Số ký hiệu VB, Trích yếu VB, Nội dung trả lời, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID.

---

## Attachment format (trong response)

```json
{
  "id": 1,
  "media_id": 123,
  "file_name": "don-thu.pdf",
  "sort_order": 0,
  "url": "/storage/123/don-thu.pdf",
  "original_name": "don-thu.pdf",
  "mime_type": "application/pdf",
  "size": 102400
}
```

---

## Source

- Controller: `app/Modules/TaskAssignment/Controllers/TaskAssignmentPetitionController.php`
- Service: `app/Modules/TaskAssignment/Services/TaskAssignmentPetitionService.php`
- Model: `app/Modules/TaskAssignment/Models/TaskAssignmentPetition.php`
- Routes: `app/Modules/TaskAssignment/Routes/task_assignment_petition.php`
- Export: `app/Modules/TaskAssignment/Exports/PetitionsExport.php`
- Enum: `app/Modules/TaskAssignment/Enums/PetitionStatusEnum.php`
