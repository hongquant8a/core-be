# Đơn thư (TaskAssignment Petitions) — Changelog cho FE

## Tổng quan

Module quản lý đơn thư trong phạm vi TaskAssignment. Base URL: `/api/task-assignment-petitions`

Auth: `Authorization: Bearer {token}` + `X-Organization-Id: {org_id}`

---

## Danh sách endpoint

| # | Method | Endpoint | Permission | Mô tả |
|---|---|---|---|---|
| 1 | `GET` | `/api/task-assignment-petitions/stats` | `task-assignment-petitions.index` | Thống kê đơn thư |
| 2 | `GET` | `/api/task-assignment-petitions` | `task-assignment-petitions.index` | Danh sách đơn thư |
| 3 | `GET` | `/api/task-assignment-petitions/export` | `task-assignment-petitions.export` | Xuất Excel |
| 4 | `GET` | `/api/task-assignment-petitions/{petition}` | `task-assignment-petitions.show` | Chi tiết đơn thư |
| 5 | `POST` | `/api/task-assignment-petitions` | `task-assignment-petitions.store` | Tạo đơn thư |
| 6 | `PUT` | `/api/task-assignment-petitions/{petition}` | `task-assignment-petitions.update` | Sửa đơn thư |
| 7 | `PATCH` | `/api/task-assignment-petitions/{petition}/status` | `task-assignment-petitions.changeStatus` | Đổi trạng thái |
| 8 | `PATCH` | `/api/task-assignment-petitions/{petition}/progress` | `task-assignment-petitions.update` | Cập nhật tiến độ xử lý |
| 9 | `DELETE` | `/api/task-assignment-petitions/{petition}` | `task-assignment-petitions.destroy` | Xóa đơn thư |
| 10 | `DELETE` | `/api/task-assignment-petitions/bulk-delete` | `task-assignment-petitions.bulkDestroy` | Xóa hàng loạt |

---

## 1. GET /stats — Thống kê

### Query params (giống index)

| Param | Type | Mô tả |
|---|---|---|
| `search` | string | Tìm theo tên, CCCD, SĐT, email, nội dung |
| `processing_status` | string | `new` / `processing` / `completed` / `paused` / `cancelled` |
| `department_id` | int | ID phòng ban |
| `submission_date_from` | date | Ngày gửi từ (Y-m-d) |
| `submission_date_to` | date | Ngày gửi đến (Y-m-d) |
| `deadline_date_from` | date | Hạn xử lý từ |
| `deadline_date_to` | date | Hạn xử lý đến |

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

## 2. GET / — Danh sách

### Query params (đầy đủ)

| Param | Type | Default | Mô tả |
|---|---|---|---|
| `search` | string | | Tìm theo sender_name, sender_cccd, sender_phone, sender_email, content |
| `processing_status` | string | | `new`, `processing`, `completed`, `paused`, `cancelled` |
| `department_id` | int | | ID phòng ban |
| `submission_date_from` | date | | Ngày gửi đơn từ (Y-m-d) |
| `submission_date_to` | date | | Ngày gửi đơn đến (Y-m-d) |
| `deadline_date_from` | date | | Hạn xử lý từ (Y-m-d) |
| `deadline_date_to` | date | | Hạn xử lý đến (Y-m-d) |
| `sort_by` | string | `id` | `id`, `submission_date`, `deadline_date`, `created_at`, `updated_at` |
| `sort_order` | string | `desc` | `asc` / `desc` |
| `limit` | int | `20` | Số bản ghi/trang |

### Response (pagination)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "department_id": 1,
      "department": { "id": 1, "name": "Văn phòng" },
      "submission_date": "10/06/2026",
      "deadline_date": "15/06/2026",
      "sender_name": "Nguyễn Văn A",
      "sender_address": "Số 1, đường A, TP.HCM",
      "sender_cccd": "079202001234",
      "sender_phone": "0901234567",
      "sender_email": "nguyenvana@example.com",
      "content": "Nội dung đơn...",
      "processing_status": "new",
      "timing_status": "upcoming",
      "is_overdue": false,
      "completed_at": null,
      "document_number": null,
      "document_excerpt": null,
      "response_content": null,
      "attachments": [
        {
          "id": 1,
          "media_id": 123,
          "file_name": "don-thu.pdf",
          "type": "petition",
          "sort_order": 0,
          "url": "/storage/123/don-thu.pdf",
          "original_name": "don-thu.pdf",
          "mime_type": "application/pdf",
          "size": 102400
        }
      ],
      "created_by": { "id": 1, "name": "Admin" },
      "updated_by": null,
      "created_at": "10:00:00 10/06/2026",
      "updated_at": "10:00:00 10/06/2026"
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 25, "last_page": 2 }
  }
}
```

### Giải thích các trường

#### `processing_status` (Trạng thái xử lý)

| Value | Label |
|---|---|
| `new` | Mới tiếp nhận |
| `processing` | Đang xử lý |
| `completed` | Đã hoàn thành |
| `paused` | Tạm dừng |
| `cancelled` | Đã hủy |

#### `timing_status` (Trạng thái thời gian — computed, BE trả về)

| Value | Ý nghĩa |
|---|---|
| `upcoming` | Chưa đến hạn |
| `early` | Đã hoàn thành, sớm hơn hạn |
| `on_time` | Đã hoàn thành đúng hạn |
| `late` | Đã hoàn thành trễ hạn |
| `overdue` | Chưa hoàn thành, đã quá hạn |
| `cancelled` | Đã hủy |

#### `is_overdue` (boolean)

`true` khi chưa hoàn thành / chưa hủy VÀ đã quá `deadline_date`.

#### `attachments[].type`

| Value | Ý nghĩa |
|---|---|
| `petition` | Đính kèm của đơn thư (upload lúc tạo/sửa đơn) |
| `progress` | Đính kèm trả lời (upload lúc cập nhật tiến độ) |

---

## 3. GET /export — Xuất Excel

Cùng query params với index. Trả về file `.xlsx`.

Các cột export: STT, Người gửi đơn, CCCD, Số điện thoại, Email, Địa chỉ, Nội dung đơn, Ngày gửi đơn, Hạn xử lý, Phòng ban, Trạng thái, Ngày hoàn thành, Số ký hiệu VB, Trích yếu VB, Nội dung trả lời, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID.

---

## 4. GET /{petition} — Chi tiết

Response format giống 1 item trong danh sách, có đầy đủ attachments.

---

## 5. POST / — Tạo đơn thư

Content-Type: `multipart/form-data`

### Body params

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `submission_date` | date | **Yes** | Ngày gửi đơn (Y-m-d) |
| `sender_name` | string | **Yes** | Tên người gửi đơn (max 255) |
| `deadline_date` | date | No | Hạn xử lý (Y-m-d), phải >= submission_date |
| `sender_address` | string | No | Địa chỉ (max 500) |
| `sender_cccd` | string | No | CCCD (max 20) |
| `sender_phone` | string | No | SĐT (max 30) |
| `sender_email` | email | No | Email |
| `content` | string | No | Nội dung đơn |
| `attachments[]` | file | No | File đính kèm (max 10 file, mỗi file ≤20MB, định dạng: pdf/doc/docx/xls/xlsx/ppt/pptx/jpg/png/gif) |

**Lưu ý quan trọng:**

- **KHÔNG** gửi `department_id`. BE tự gán phòng ban hiện tại của user đang đăng nhập (từ `taskAssignmentUser.task_assignment_department_id`).
- Không gửi `processing_status` — mặc định `new`.
- File đính kèm được upload lúc này sẽ có `type: "petition"`.

### Response

```json
{
  "success": true,
  "message": "Tạo đơn thư thành công!",
  "data": { "id": 1, ... }
}
```

---

## 6. PUT /{petition} — Sửa đơn thư

Content-Type: `multipart/form-data`

### Body params

Tất cả field của store nhưng không required (gửi field nào cập nhật field đó). Bổ sung:

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `processing_status` | string | No | Trạng thái mới. Nếu = `completed` → BE tự set `completed_at = now()` (nếu chưa có) |
| `completed_at` | datetime | No | Ngày hoàn thành (nếu không gửi và status=`completed` → auto now()) |
| `document_number` | string | No | Số ký hiệu văn bản trả lời (max 255) |
| `document_excerpt` | string | No | Trích yếu văn bản (max 2000) |
| `response_content` | string | No | Tóm tắt nội dung trả lời |
| `attachments[]` | file | No | File đính kèm mới (type = `petition`) |
| `remove_attachment_ids[]` | int[] | No | DS ID attachment cần xóa (chỉ xóa attachment có type = `petition`) |

**Lưu ý:** `PUT /{petition}` dùng để sửa thông tin đơn thư và upload các file đính kèm của đơn (type = `petition`). Để cập nhật tiến độ xử lý (nội dung trả lời + đính kèm trả lời), dùng `PATCH /{petition}/progress`.

---

## 7. PATCH /{petition}/status — Đổi trạng thái

### Body params

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `processing_status` | string | **Yes** | `new` / `processing` / `completed` / `paused` / `cancelled` |

**Hành vi:**
- Chuyển sang `completed` → BE tự set `completed_at = now()`
- Chuyển từ `completed` sang trạng thái khác → BE xóa `completed_at`

---

## 8. PATCH /{petition}/progress — Cập nhật tiến độ xử lý

Permission: `task-assignment-petitions.update` (dùng chung với update)

Content-Type: `multipart/form-data`

### Body params

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `completed_at` | datetime | No | Ngày hoàn thành xử lý |
| `document_number` | string | No | Số ký hiệu văn bản trả lời (max 255) |
| `document_excerpt` | string | No | Trích yếu văn bản (max 2000) |
| `response_content` | string | No | Tóm tắt nội dung trả lời |
| `attachments[]` | file | No | File đính kèm trả lời (max 10, ≤20MB) |
| `remove_attachment_ids[]` | int[] | No | DS ID attachment cần xóa (xóa cả 2 loại `petition` và `progress`) |

**Khác biệt với `PUT /{petition}`:**
- Endpoint này dành riêng cho cập nhật kết quả xử lý (nội dung trả lời, VB, đính kèm trả lời).
- File upload qua endpoint này có `type: "progress"` (phân biệt với `type: "petition"` ở store/update).

### Response

```json
{
  "success": true,
  "message": "Cập nhật tiến độ thành công!",
  "data": { "id": 1, ... }
}
```

---

## 9. DELETE /{petition} — Xóa đơn thư

Xóa cứng (hard delete) kèm file đính kèm và media.

---

## 10. DELETE /bulk-delete — Xóa hàng loạt

### Body params (JSON)

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `ids` | int[] | **Yes** | Danh sách ID cần xóa |

---

## Phòng ban & `is_petition_overview`

- Bảng `task_assignment_departments` có thêm cột `is_petition_overview` (boolean, default `false`).
- Khi tạo/sửa phòng ban (API Department cũ), FE có thể gửi `is_petition_overview: true` để đánh dấu phòng ban được xem tổng hợp đơn thư.
- Trong response của Department API có field `is_petition_overview` (boolean).
- **Phòng ban có `is_petition_overview = true`**: thấy toàn bộ đơn thư của tất cả phòng ban.
- **Phòng ban thường**: chỉ thấy đơn thư của phòng ban mình.

---

## Tóm tắt khác biệt giữa PUT /{petition} và PATCH /{petition}/progress

| | PUT /{petition} | PATCH /{petition}/progress |
|---|---|---|
| **Mục đích** | Sửa thông tin đơn thư | Cập nhật kết quả xử lý |
| **Sửa field đơn** | Có (submission_date, sender_name, ...) | Không |
| **Sửa field xử lý** | Có (nhưng khuyến nghị dùng progress) | Có (completed_at, document_number, ...) |
| **File upload type** | `petition` | `progress` |
| **Xóa attachment** | Chỉ xóa attachment type `petition` | Xóa cả 2 type |
| **Permission** | `update` | `update` (dùng chung) |

Khuyến nghị: dùng PUT để sửa thông tin đơn, dùng PATCH progress để cập nhật kết quả xử lý + đính kèm trả lời.
