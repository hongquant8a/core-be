# Sắp xếp lịch công tác (Reorder)

> Xem tổng quan API module Scheduling tại [api_docs_scheduling_module.md](api_docs_scheduling_module.md).

## `PATCH /api/schedules/reorder`

Dùng cho kéo thả sắp xếp lại vị trí các lịch trong cùng một ngày + buổi. Gửi danh sách ID theo thứ tự mong muốn, BE gán lại `sort_order` từ 1 → N.

### Permission
`schedules-{executive|office}.update`

### Query params

| Param | Kiểu | Required | Mô tả |
|---|---|---|---|
| `module_type` | string | required | Phân hệ: `EXECUTIVE` hoặc `OFFICE` |

### Request body

```json
{
  "ordered_ids": [3, 1, 2]
}
```

| Field | Kiểu | Mô tả |
|---|---|---|
| `ordered_ids` | int[] | Danh sách ID lịch theo thứ tự mới. `sort_order` sẽ được gán 1, 2, 3... theo index trong mảng. |

### Response

```json
// 200
{
  "success": true,
  "message": "Sắp xếp lịch công tác thành công!"
}
```

### Ví dụ

Giả sử thứ 2 buổi sáng có 3 lịch với sort_order hiện tại:

| sort_order | id | content |
|---|---|---|
| 1 | 41 | Họp giao ban |
| 2 | 42 | Tiếp khách |
| 3 | 43 | Họp chi bộ |

FE kéo "Họp chi bộ" (id=43) lên đầu:

```
PATCH /api/schedules/reorder?module_type=EXECUTIVE
{ "ordered_ids": [43, 41, 42] }
```

Kết quả:

| sort_order | id | content |
|---|---|---|
| 1 | 43 | Họp chi bộ |
| 2 | 41 | Họp giao ban |
| 3 | 42 | Tiếp khách |

### Ghi chú

- Chỉ thay đổi `sort_order`, không thay đổi `date_time`, `session`, hay nội dung.
- Các ID không có trong `ordered_ids` sẽ không bị ảnh hưởng.
