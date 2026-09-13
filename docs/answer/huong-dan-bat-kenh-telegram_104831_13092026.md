# Hướng dẫn bật kênh thông báo Telegram

> Ngày tạo: 10:48:31 13/09/2026  
> Cập nhật lần cuối: 10:48:31 13/09/2026

Dành cho người vận hành. Làm một lần cho toàn hệ thống, trừ bước 4 là việc của từng nhân viên.

Tài liệu kỹ thuật: [`docs/api/telegram.md`](../api/telegram.md)

---

## Bước 0 — Trên máy chủ

```bash
git pull
sail artisan migrate
sail artisan db:seed --class=SettingSeeder
```

## Bước 1 — Tạo bot (làm trong app Telegram)

Mở Telegram → tìm `@BotFather` (tài khoản có dấu tích xanh) → lần lượt:

| Gõ / nhập | Ví dụ |
|---|---|
| `/newbot` | |
| Tên hiển thị | `QLCV Danatec` |
| Username, **bắt buộc kết thúc bằng `bot`** | `danatec_qlcv_bot` |

BotFather trả về chuỗi dạng `123456789:AAE...` — đó là Bot Token.

**Giữ token như mật khẩu.** Ai cầm được cũng gửi được tin nhắn dưới danh nghĩa hệ thống.
Không gửi qua chat nhóm, không lưu vào tài liệu dùng chung. Lỡ lộ thì vào BotFather gõ
`/revoke` lấy token mới rồi cập nhật lại trong màn Cài đặt.

## Bước 2 — Cấu hình hệ thống → Cấu hình thông báo → tab Telegram

Đường dẫn: `/system/settings/notification`

| Ô | Điền gì |
|---|---|
| Bật Telegram | Bật |
| Bot Token | Chuỗi BotFather vừa trả về |
| Username Bot | Để trống — hệ thống tự lấy khi đăng ký webhook |
| Domain Webhook | Địa chỉ HTTPS của backend, vd `https://api-qlcv.danatec.vn`. Để trống thì lấy `APP_URL` |
| Khóa bí mật Webhook | Để trống — tự sinh khi đăng ký |

Bấm **Lưu**, rồi bấm **Đăng ký webhook**. Thành công sẽ hiện tên bot và URL đã đăng ký;
hỏng thì hiện nguyên văn lý do Telegram trả về.

Đây là bước quyết định: chưa đăng ký webhook thì nhân viên bấm START xong hệ thống không hề
biết, vì Telegram không có cách nào cho mình hỏi ngược "ai vừa nhắn cho bot của tôi".

Telegram chỉ nhận HTTPS và phải gọi vào được từ Internet. Máy phát triển dùng `localhost`
sẽ bị từ chối — muốn thử thì dựng ngrok/cloudflared rồi điền URL tạm vào ô Domain Webhook.

## Bước 3 — Chọn thông báo nào đẩy qua Telegram

**Quản lý công việc → Cấu hình thông báo**: với mỗi sự kiện muốn đẩy, tick thêm kênh
**Telegram** (kênh cũ giữ nguyên). Tick ở cả dòng *tức thì* lẫn các *mốc nhắc lịch* nếu
muốn cả hai. Màn **Cuộc họp → Cấu hình thông báo** làm tương tự.

Giai đoạn đầu chỉ nên bật nhóm quan trọng: giao việc, nhắc sắp đến hạn, quá hạn, trả lại
báo cáo. Bật tất cả sẽ làm phiền, mà người bị làm phiền thì chặn bot — chặn rồi thì hệ
thống tự gỡ liên kết và họ không nhận được gì nữa.

## Bước 4 — Nhân viên tự liên kết

Trang cá nhân → tab **Thông báo** → khối "Thông báo Telegram" → **Liên kết Telegram** →
quét mã QR (hoặc bấm "Mở Telegram" nếu đang dùng điện thoại) → bấm **START** trong cửa sổ
chat. Quay lại trang, trạng thái tự chuyển sang "Đã liên kết".

Đường dẫn sống 24 giờ. Hết hạn thì bấm tạo lại — mỗi lần tạo sẽ vô hiệu hoá đường dẫn cũ.

Nhân viên không cần biết `chat_id` là gì. Ô nhập `chat_id` tay trong màn quản trị người dùng
chỉ là lối dự phòng cho người không tự liên kết được; nhập sai một chữ số là thông báo nội
bộ gửi sang người ngoài, nên nhập xong phải gửi tin thử để xác minh.

## Bước 5 — Kiểm tra

1. Trong khối vừa liên kết, bấm **Gửi tin thử** — nhận được tin là xong.
2. Tạo thử một công việc giao cho chính mình, xem tin có về không.
3. Không thấy gì thì mở **Quản lý công việc → Nhật ký thông báo**, lọc kênh Telegram:

| Trạng thái | Nghĩa là |
|---|---|
| `sent` | Đã gửi tới Telegram |
| `skipped` | Người nhận chưa liên kết Telegram |
| `failed` | Có lỗi, cột lý do ghi nguyên văn phản hồi của Telegram |

## Những việc hệ thống tự làm, không cần can thiệp

- Nhân viên chặn bot hoặc xoá tài khoản Telegram → lần gửi kế tiếp hệ thống nhận lỗi vĩnh
  viễn, tự xoá liên kết và thôi gửi. Giao diện của người đó quay về trạng thái chưa liên kết.
- Tài khoản bị chuyển khỏi trạng thái "Đang hoạt động" (nghỉ việc, bị khoá) → gỡ liên kết
  ngay, kể cả khi đổi bằng thao tác hàng loạt hay import. Người đã nghỉ không nhận thêm
  thông báo nội bộ nào.
- Token liên kết quá hạn tự mất hiệu lực, không cần dọn.

## Khi nào phải làm lại bước 2

Đổi domain backend, đổi sang bot khác, hoặc xoay khóa bí mật — cả ba đều cần bấm lại
**Đăng ký webhook**. Không bấm lại thì nhân viên mới sẽ không liên kết được, trong khi
người đã liên kết vẫn nhận tin bình thường, nên lỗi này dễ bị bỏ qua một thời gian dài.
