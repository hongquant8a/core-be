# Liên kết Telegram bằng deep link — Backend đã xong, FE đã làm cùng đợt

> Ngày tạo: 12/09/2026

Trước đây muốn nhận thông báo Telegram thì phải tự đi tìm `chat_id` ở `@userinfobot`
rồi dán vào hồ sơ, hoặc nhờ admin nhập hộ. Giờ nhân viên bấm một nút trong trang cá
nhân, quét mã, bấm START — hệ thống tự lấy `chat_id` từ chính Telegram.

Chi tiết API: [`docs/api/telegram.md`](../api/telegram.md)

---

## 1. Endpoint mới

| Thao tác | Method | Đường dẫn | Xác thực |
|---|---|---|---|
| Trạng thái liên kết của mình | `GET` | `/api/users/me/telegram` | Sanctum |
| Tạo đường dẫn liên kết | `POST` | `/api/users/me/telegram/link` | Sanctum, throttle 5/10 phút |
| Gửi tin nhắn thử | `POST` | `/api/users/me/telegram/test` | Sanctum, throttle 5/10 phút |
| Hủy liên kết | `DELETE` | `/api/users/me/telegram` | Sanctum |
| Webhook Telegram | `POST` | `/api/telegram/webhook` | Công khai, xác thực header bí mật |
| Đăng ký webhook với Telegram | `POST` | `/api/settings/telegram/webhook` | Sanctum, quyền `settings.update` |

Không có quyền Spatie nào mới: đây là self endpoint, mỗi người chỉ thao tác với tài
khoản đang đăng nhập.

## 2. Hợp đồng API có đổi

`PUT /api/users/me` **bỏ qua** `telegram_chat_id` nếu client gửi lên. `chat_id` chỉ đến
từ Telegram; cho tự nhập thì gõ nhầm một chữ số là thông báo nội bộ chạy sang máy người
lạ, mà người gõ không có cách nào biết.

Ô nhập tay ở màn quản trị (`PUT /api/users/{user}`, quyền `users.update`) vẫn còn, nhưng
là lối dự phòng và đã có cảnh báo ngay cạnh ô nhập.

## 3. Giao diện đã làm

| Nơi | Việc |
|---|---|
| Trang cá nhân → tab Thông báo | Thẻ "Thông báo Telegram": nút liên kết → mã QR + nút mở Telegram + hạn dùng; đã liên kết thì hiện thời điểm, nút gửi tin thử, nút hủy |
| Màn quản trị người dùng | Cảnh báo rủi ro nhập nhầm `chat_id` ngay trên ô nhập tay |
| Màn Cài đặt → Telegram | Nút **Đăng ký webhook**: lưu cấu hình rồi báo địa chỉ webhook cho Telegram, khỏi phải vào máy chủ gõ lệnh |

Mã QR dựng phía trình duyệt bằng `qrcode` (đã có sẵn trong dự án). Sau khi mở link, thẻ
hỏi lại trạng thái mỗi 5 giây rồi tự đổi giao diện — người dùng đang ở bên cửa sổ Telegram,
quay lại mà vẫn thấy "chưa liên kết" thì tưởng hỏng và bấm lại từ đầu.

Người dùng không bao giờ nhìn thấy hay phải copy `chat_id`.

## 4. Cấu hình cần nhập trước khi dùng

Nhóm `telegram` trong màn Cài đặt có thêm 3 khoá: `tg_bot_username` (tự điền được),
`tg_webhook_secret` (lệnh đăng ký tự sinh), `tg_webhook_url` (bỏ trống thì lấy `APP_URL`).

Sau khi kéo code:

```bash
sail artisan migrate
sail artisan db:seed --class=SettingSeeder
```

Rồi vào Cài đặt → Telegram: nhập Bot Token, bật kênh, nhập Domain Webhook (bỏ trống thì lấy
`APP_URL`), bấm **Đăng ký webhook**. Lệnh `sail artisan telegram:set-webhook` vẫn còn cho
triển khai tự động.

## 5. Hành vi tự dọn

- Người dùng chặn bot → lần gửi kế tiếp nhận lỗi vĩnh viễn → hệ thống xoá `chat_id`, thẻ
  trên giao diện tự quay về trạng thái chưa liên kết.
- Tài khoản bị chuyển khỏi trạng thái `active` → gỡ liên kết ngay, kể cả khi đổi bằng
  thao tác hàng loạt hay import.

## 6. Chưa làm (giai đoạn sau)

Trả lời/thao tác ngược từ Telegram, gửi vào group phòng ban, đính kèm file, inline keyboard,
thống kê tỷ lệ liên kết theo phòng ban.
