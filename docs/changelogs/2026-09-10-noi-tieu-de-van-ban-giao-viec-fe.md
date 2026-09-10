# Nới trần độ dài tiêu đề văn bản giao việc

> Ngày tạo: 15:25:00 10/09/2026  
> Cập nhật lần cuối: 15:25:00 10/09/2026

Tiêu đề văn bản giao việc trước đây bị chặn ở 255 ký tự. Văn bản hành chính thật gói cả số
hiệu, ngày ban hành, cơ quan ban hành và trích yếu vào tiêu đề, ví dụ:

> Thông báo số 315/TB-VP - 04/08/2026 - UBND Phường Hòa Khánh
> kết luận của đồng chí Đặng Ngọc Tuấn Phó Chủ tịch Thường trực UBND phường, Chủ tịch Hội
> đồng Bồi thường thiệt hại, bố trí TĐC tại buổi kiểm tra và tiếp dân…

nên vượt 255 là chuyện bình thường — 17/100 văn bản của phường Hoà Khánh dài 257–365 ký tự.
Cột đã đổi sang `TEXT`, giống việc đã làm cho tên công việc ngày 19/08/2026.

---

## Thay đổi

| | Trước | Sau |
|---|---|---|
| Cột `task_assignment_documents.name` | `varchar(255)` | `text` |
| Luật `name` ở tạo/sửa văn bản | `max:255` | `max:20000` |

`max:20000` đếm **ký tự**, trong khi `TEXT` giới hạn theo **byte** (65.535). Tiếng Việt có dấu
tốn tới 3 byte/ký tự nên 20.000 ký tự vẫn nằm dưới trần. Mục đích của luật này chỉ là trả 422
sạch thay vì để MySQL strict mode ném SQLSTATE 22001 thành lỗi 500.

## Việc cần làm ở FE

1. **Bỏ `maxlength="255"`** trên input tiêu đề văn bản nếu đang đặt (hiện chưa thấy chỗ nào
   đặt, kiểm tra lại cho chắc).
2. **Đổi input thành textarea** ở form tạo/sửa văn bản. Tiêu đề dài 300+ ký tự và có ký tự
   xuống dòng (`\r\n`) — input một dòng sẽ cắt mất phần nhìn thấy.
3. **Chỗ hiển thị danh sách**: tiêu đề dài cần `line-clamp` hoặc `text-overflow: ellipsis`,
   kèm `title` để hover xem đầy đủ. Không để tiêu đề đẩy vỡ layout bảng.
4. **Chi tiết văn bản**: hiển thị tiêu đề nhiều dòng, giữ ký tự xuống dòng (`white-space:
   pre-line`).

## Không thay đổi

- Đường dẫn API, tên trường, định dạng response — giữ nguyên.
- `summary` vẫn là `text`, `max:65535` như cũ.
