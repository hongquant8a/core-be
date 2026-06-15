# Big Update: Luồng "Chờ duyệt" (pending_approval)

## 1. Trạng thái mới: `pending_approval` (Chờ duyệt)

Trước đây: `todo → in_progress → done`

Nay: `todo → in_progress → pending_approval → done`

| Status            | Nhãn             | Ai set       | Ghi chú                             |
|-------------------|------------------|--------------|-------------------------------------|
| `todo`            | Chưa bắt đầu     | Hệ thống     |                                     |
| `in_progress`     | Đang thực hiện   | Hệ thống     |                                     |
| `pending_approval`| Chờ duyệt        | Hệ thống     | **Không có trong dropdown FE**       |
| `done`            | Hoàn thành       | Manager      | Chỉ qua `mark-done`                 |
| `paused`          | Tạm dừng         | Người dùng   |                                     |
| `cancelled`       | Đã hủy           | Người dùng   |                                     |

> `pending_approval` và `done` không nằm trong `selectableValues()` của enum — FE không cần hiển thị trong dropdown chọn trạng thái.


## 2. Flow chính thay đổi

### 2.1. Cập nhật tiến độ — `PATCH /{id}/progress`
```
Body: { "completion_percent": 80 }   (processing_status là optional)

BE tự suy status:
  0%     → todo
  1-100% → in_progress
```

> **Đã bỏ:** Trước đây kéo lên 100% là auto `pending_approval`. Nay **không còn nữa**.
> `updateProgress` chỉ cập nhật tiến độ, không bao giờ tự chuyển `pending_approval`.

### 2.2. Submit báo cáo — `POST /api/task-assignment-item-reports` ← KEY STEP
```
Body: {
  "task_assignment_item_id": 1,       // required
  "completion_percent": 100,          // optional, 0-100
  "completed_at": "2026-06-15",       // optional
  "report_document_number": "...",    // optional
  "report_document_excerpt": "...",   // optional
  "report_document_content": "...",   // optional
  "attachments[]": ...                // optional, file
}
```

**Khi `completion_percent >= 100`:** BE tự động:
- Set `item.completion_percent = 100`
- Chuyển `item.processing_status = pending_approval`
- Ghi nhận `item.reported_at = now`, `item.reported_by = current_user`

> Đây là **cách duy nhất** để chuyển công việc sang `pending_approval`.
> Submit báo cáo **không giới hạn số lượt**. Mỗi lần submit là 1 report mới.

### 2.3. Duyệt hoàn thành — `PATCH /{id}/mark-done`
- Chỉ chấp nhận khi item đang ở `pending_approval`
- Set `processing_status = done`, `completion_percent = 100`, `approved_by = current_user`, `completed_at = now`
- Nếu gọi khi item **không** ở `pending_approval` → lỗi 500: *"Công việc đang ở trạng thái X — chỉ có thể đánh dấu hoàn thành khi đang chờ duyệt"*

### 2.4. Từ chối duyệt — `PATCH /{id}/reject` ← NEW
```
Body: { "rejection_reason": "Lý do từ chối..." }   // required, max 5000 ký tự
```
- Chỉ chấp nhận khi item đang ở `pending_approval`
- Chuyển `processing_status = in_progress`, lưu `rejection_reason`
- Employee có thể sửa lại và submit báo cáo mới (tạo report mới + completion_percent=100 → lại lên pending_approval)

### 2.5. Mở lại — `PATCH /{id}/reopen`
Dùng khi công việc đang ở trạng thái "đóng" (`done`, `cancelled`, `paused`).

Tự suy status từ `completion_percent` hiện tại:
```
completion_percent >= 100 → pending_approval
completion_percent > 0   → in_progress
completion_percent = 0   → todo
```


## 3. Response có thêm field mới

Item response nay có thêm:
```json
{
  "rejection_reason": "Thiếu tài liệu đính kèm",   // string | null
  "reported_at": "14:30:00 15/06/2026",            // string | null — thời điểm submit báo cáo 100%
  "reported_by": { "id": 1, "name": "Nguyễn Văn A", ... },  // object | null — người submit báo cáo
  "approved_by": { "id": 2, "name": "Trần Văn B", ... }     // object | null — người duyệt mark-done
}
```


## 4. Thống kê có thêm bucket `pending_approval`

Tất cả API stats đều có thêm key `pending_approval`:

- `GET /stats` → `{ "pending_approval": 3, ... }`
- `GET /stats-by-item-type` → mỗi item type có `"pending_approval": N`
- `GET /stats-by-department` → mỗi department có `"pending_approval": N`
- `GET /stats-by-user` → mỗi user có `"pending_approval": N`
- `GET /stats-by-time` → mỗi tháng có `"pending_approval": N`
- `GET /stats-by-document` → mỗi document có `"pending_approval": N`

Bucket `pending_approval` **được tính vào overdue** (công việc chờ duyệt vẫn có thể quá hạn).


## 5. Tóm tắt cho FE

| Việc cần làm | Gọi API gì | Ghi chú |
|---|---|---|
| Kéo progress | `PATCH /{id}/progress` | Chỉ gửi `completion_percent`, không tự lên chờ duyệt |
| Submit báo cáo | `POST /task-assignment-item-reports` | Gửi kèm `completion_percent: 100` để chuyển sang chờ duyệt |
| Duyệt hoàn thành | `PATCH /{id}/mark-done` | Chỉ được khi đang `pending_approval` |
| Từ chối duyệt | `PATCH /{id}/reject` | Body: `{ "rejection_reason": "..." }` |
| Mở lại | `PATCH /{id}/reopen` | Dựa vào `completion_percent` để suy status mới |

**Giao diện:**
- Submit báo cáo: form có field `completion_percent` (optional), nếu là 100% → hiển thị cảnh báo "Công việc sẽ chuyển sang chờ duyệt"
- Manager thấy item `pending_approval` → hiện nút "Duyệt" + "Từ chối"
- Nút "Từ chối" → mở popup nhập lý do (required)
- Nút "Mở lại" → hiện khi status là `paused`, `cancelled`, `done`
