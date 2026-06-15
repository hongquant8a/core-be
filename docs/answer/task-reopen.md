# API Mở lại công việc (Reopen)

## Endpoint

```
PATCH /api/task-assignment-items/{id}/reopen
```

- **Auth:** Yêu cầu đăng nhập + permission `task-assignment-items.changeStatus`
- **Header:** `X-Organization-Id` (bắt buộc)
- **Body:** Không cần body

## Response

```json
{
  "success": true,
  "message": "Đã mở lại công việc!",
  "data": {
    "id": 1,
    "processing_status": "in_progress",
    "completion_percent": 50,
    ...
  }
}
```

## Logic BE

Trạng thái mới được tự động suy từ `completion_percent` hiện tại của công việc:

| `completion_percent` | Trạng thái sau reopen | Nhãn           |
|-----------------------|------------------------|----------------|
| `0`                   | `todo`                 | Chưa bắt đầu    |
| `1` – `99`            | `in_progress`          | Đang thực hiện  |
| `100`                 | `pending_approval`     | Chờ duyệt       |

> `done` chỉ đạt được khi manager gọi `PATCH /{id}/mark-done` (chỉ từ `pending_approval`).

## Quy tắc FE

1. **Nút "Mở lại"** hiển thị khi công việc đang ở trạng thái "đóng": `done`, `cancelled`, hoặc `paused`.
2. Bấm nút → gọi `PATCH /api/task-assignment-items/{id}/reopen` (không cần truyền body).
3. Sau khi gọi thành công, cập nhật UI với `processing_status` và `completion_percent` từ response.

> Xem thêm luồng đầy đủ tại: [task-pending-approval-flow.md](task-pending-approval-flow.md)
