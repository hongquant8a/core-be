# Hướng dẫn FE: Đơn thư theo phòng ban (multi-department)

## Tóm tắt

Một user có thể thuộc nhiều phòng ban. API trả đơn thư theo đúng phòng user được gán.

---

## 1. Luồng FE khi vào màn hình đơn thư

```
B1. Gọi GET /api/task-assignment-petitions/available-departments
    ↓
    ┌─ 0 phòng → hiện thông báo "Bạn chưa được phân vào phòng nào" → không gọi tiếp
    ├─ 1 phòng → tự chọn phòng đó, không cần hiện select
    └─ 2+ phòng → hiện dropdown select chọn phòng (mặc định chọn tất cả)
    
B2. Gọi GET /api/task-assignment-petitions?department_id=X (nếu user chọn 1 phòng)
    hoặc GET /api/task-assignment-petitions (xem tất cả phòng của user)
    
B3. Khi tạo mới: phải gửi department_id (bắt buộc)
```

---

## 2. API: Danh sách phòng ban của user

```
GET /api/task-assignment-petitions/available-departments
Auth: required (header Authorization: Bearer <token>)
```

### Response (thành công)

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Phòng Hành chính" },
    { "id": 3, "name": "Phòng Tư pháp" }
  ]
}
```

### Logic FE

| Số phòng | Hành động |
|---|---|
| 0 | Không gọi index/stats. Hiển thị thông báo. KHÔNG hiển thị form tạo mới. |
| 1 | Ẩn select. Gọi index không cần `department_id` (BE tự filter). |
| 2+ | Hiện select: option "Tất cả phòng" (value rỗng) + từng phòng. Khi chọn "Tất cả" → gọi index không `department_id`. Khi chọn 1 phòng → `?department_id=X`. |

---

## 3. API: Danh sách đơn thư

```
GET /api/task-assignment-petitions?limit=20&page=1
Auth: required
```

### Query params

| Param | Type | Mô tả |
|---|---|---|
| `search` | string | Tìm theo họ tên, CCCD, SĐT, email, nội dung |
| `processing_status` | string | Lọc trạng thái: `new`, `processing`, `completed`, `paused`, `cancelled` |
| `department_id` | int | Lọc theo 1 phòng. Nếu không truyền → BE tự quyết |
| `submission_date_from` | date | Ngày gửi từ (Y-m-d) |
| `submission_date_to` | date | Ngày gửi đến (Y-m-d) |
| `deadline_date_from` | date | Hạn xử lý từ (Y-m-d) |
| `deadline_date_to` | date | Hạn xử lý đến (Y-m-d) |
| `sort_by` | string | `id`, `submission_date`, `deadline_date`, `created_at`, `updated_at` |
| `sort_order` | string | `asc` / `desc` (mặc định: desc) |
| `limit` | int | Số bản ghi/trang (mặc định: 20) |

### Logic BE tự động

Không cần FE lo — BE tự xử lý:

- User 0 phòng → luôn trả rỗng
- User 1 phòng → tự filter theo phòng đó
- User 2+ phòng → nếu không có `department_id` → trả đơn của tất cả phòng user
- User có phòng `is_petition_overview = true` → thấy toàn bộ đơn (không restrict)
- Nếu FE gửi `department_id` không thuộc user → BE trả rỗng

### Response

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "department_id": 1,
      "department": { "id": 1, "name": "Phòng Hành chính" },
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
          "media_id": 5,
          "file_name": "don-thu.pdf",
          "type": "petition",
          "sort_order": 0,
          "url": "/storage/5/don-thu.pdf",
          "original_name": "don-thu.pdf",
          "mime_type": "application/pdf",
          "size": 204800
        }
      ],
      "created_by": { "id": 2, "name": "Admin" },
      "updated_by": { "id": 2, "name": "Admin" },
      "created_at": "14:30:00 10/06/2026",
      "updated_at": "14:30:00 10/06/2026"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 20, "total": 1 }
}
```

---

## 4. API: Thống kê

```
GET /api/task-assignment-petitions/stats
Auth: required
```

### Query params (tùy chọn)

Cùng bộ filter như index: `search`, `processing_status`, `department_id`, `submission_date_from/to`, `deadline_date_from/to`.

### Response

```json
{
  "success": true,
  "data": {
    "total": 50,
    "new": 10,
    "processing": 20,
    "completed": 15,
    "paused": 3,
    "cancelled": 2
  }
}
```

---

## 5. API: Tạo đơn thư

```
POST /api/task-assignment-petitions
Auth: required
Content-Type: multipart/form-data
```

### Body params

| Param | Type | Required | Mô tả |
|---|---|---|---|
| `department_id` | int | **Bắt buộc** | ID phòng ban tiếp nhận (từ danh sách available-departments) |
| `submission_date` | date | Bắt buộc | Ngày gửi đơn (Y-m-d) |
| `sender_name` | string | Bắt buộc | Họ tên người gửi |
| `deadline_date` | date | | Hạn xử lý (Y-m-d) |
| `sender_address` | string | | Địa chỉ |
| `sender_cccd` | string | | Số CCCD (max 20) |
| `sender_phone` | string | | SĐT (max 30) |
| `sender_email` | email | | Email |
| `content` | string | | Nội dung đơn |
| `attachments[]` | file | | File đính kèm (max 10) |

### Lưu ý FE

- **`department_id` là bắt buộc mới.** Trước đây không có param này, giờ phải gửi.
- FE lấy `department_id` từ danh sách `available-departments`.
  - 1 phòng: tự điền, không cho sửa.
  - 2+ phòng: cho chọn từ select.

### Response (201)

```json
{
  "success": true,
  "data": { ... },
  "message": "Tạo đơn thư thành công!"
}
```

---

## 6. API: Cập nhật đơn thư

```
PUT /api/task-assignment-petitions/{id}
Auth: required
Content-Type: multipart/form-data
```

| Param | Type | Mô tả |
|---|---|---|
| `department_id` | int | ID phòng ban (tùy chọn) |
| ...các trường khác giống store nhưng đều optional | | |
| `remove_attachment_ids[]` | int[] | ID attachment cần xóa |

---

## 7. Bảng trạng thái

| Value | Label |
|---|---|
| `new` | Mới tiếp nhận |
| `processing` | Đang xử lý |
| `completed` | Đã hoàn thành |
| `paused` | Tạm dừng |
| `cancelled` | Đã hủy |

## 8. Bảng timing_status

| Value | Ý nghĩa |
|---|---|
| `upcoming` | Sắp đến hạn |
| `overdue` | Quá hạn |
| `early` | Hoàn thành sớm |
| `on_time` | Hoàn thành đúng hạn |
| `late` | Hoàn thành trễ hạn |
| `cancelled` | Đã hủy |
