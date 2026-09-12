# API — Liên kết Telegram

> Ngày tạo: 17:30:00 12/09/2026  
> Cập nhật lần cuối: 17:30:00 12/09/2026

Nhân viên tự liên kết tài khoản với Telegram bằng deep link để nhận thông báo công việc.
Telegram là **kênh phụ**: thông báo luôn được ghi trong ứng dụng, chưa liên kết vẫn xem đủ.

## 1. Chuẩn bị (một lần)

| Cấu hình (nhóm `telegram`) | Ý nghĩa |
|---|---|
| `tg_enabled` | Bật/tắt toàn bộ kênh Telegram |
| `tg_bot_token` | Token bot lấy từ `@BotFather` |
| `tg_bot_username` | Username bot, dùng dựng deep link. Bỏ trống thì hệ thống tự hỏi Telegram (`getMe`) rồi điền |
| `tg_webhook_secret` | Chuỗi bí mật Telegram gửi kèm mỗi webhook. Lệnh đăng ký tự sinh nếu trống |
| `tg_webhook_url` | Domain HTTPS công khai của backend. Bỏ trống thì lấy `APP_URL` trong `.env` |

Đăng ký webhook với Telegram:

```bash
sail artisan telegram:set-webhook                              # dùng tg_webhook_url hoặc APP_URL
sail artisan telegram:set-webhook --url=https://ngrok.../      # ghi đè khi phát triển
```

Telegram chỉ chấp nhận HTTPS và phải gọi vào được từ Internet — `localhost` không dùng được,
lúc phát triển thì dựng đường hầm (ngrok, cloudflared).

## 2. Endpoint

| Thao tác | Method | Đường dẫn | Xác thực |
|---|---|---|---|
| Xem trạng thái liên kết của mình | `GET` | `/api/users/me/telegram` | Sanctum |
| Tạo đường dẫn liên kết | `POST` | `/api/users/me/telegram/link` | Sanctum, throttle 5/10 phút |
| Gửi tin nhắn thử | `POST` | `/api/users/me/telegram/test` | Sanctum, throttle 5/10 phút |
| Hủy liên kết | `DELETE` | `/api/users/me/telegram` | Sanctum |
| Webhook Telegram | `POST` | `/api/telegram/webhook` | Công khai — header `X-Telegram-Bot-Api-Secret-Token` |

Không có endpoint nào nhận `chat_id` từ phía người dùng. `PUT /api/users/me` **bỏ qua**
`telegram_chat_id` nếu client gửi lên; ô nhập tay chỉ còn ở màn quản trị
(`PUT /api/users/{user}`, quyền `users.update`) và là lối dự phòng có cảnh báo.

### Trạng thái liên kết

```json
{
  "success": true,
  "data": {
    "linked": true,
    "linked_at": "09:30:00 12/09/2026",
    "pending_link": false,
    "expires_at": null,
    "bot_username": "danatec_qlcv_bot"
  }
}
```

### Tạo đường dẫn liên kết

```json
{
  "success": true,
  "message": "Đã tạo đường dẫn liên kết.",
  "data": {
    "deep_link": "https://t.me/danatec_qlcv_bot?start=<token 32 ký tự>",
    "expires_at": "09:30:00 13/09/2026"
  }
}
```

Mỗi lần gọi vô hiệu hoá token cũ. Token sống 24 giờ và ngắn hơn giới hạn 64 ký tự
của payload `start` mà Telegram cho phép.

## 3. Luồng liên kết

```
Nhân viên bấm "Liên kết Telegram" trong trang cá nhân
  → BE sinh token, trả deep link (FE dựng mã QR phía trình duyệt)
  → nhân viên quét mã / bấm nút, Telegram mở chat bot, bấm START
  → Telegram gọi webhook kèm token + chat_id
  → BE xác thực secret, trả 200 ngay, đẩy ProcessTelegramUpdateJob vào queue `notifications`
  → job tra token ra user, lưu chat_id, gửi tin chào mừng
  → FE hỏi lại trạng thái mỗi 5 giây và tự đổi giao diện
```

Các nhánh hỏng đều có phản hồi trong Telegram, không im lặng:

| Tình huống | Bot trả lời |
|---|---|
| Bấm START không kèm token | Hướng dẫn vào ứng dụng lấy đường dẫn |
| Token sai hoặc đã dùng | Báo không hợp lệ, bảo tạo lại |
| Token hết hạn | Báo hết hạn, bảo tạo lại |
| `chat_id` đang gắn người khác | Gỡ khỏi người cũ rồi gắn cho người mới |

## 4. Tự dọn dữ liệu

| Sự kiện | Hệ thống làm gì |
|---|---|
| Người dùng chặn bot / xoá tài khoản Telegram | `SendDeliveryJob` nhận cờ lỗi vĩnh viễn từ `TelegramChannel` → xoá `telegram_chat_id`, ghi log, không gửi lại |
| Tài khoản rời trạng thái `active` | `UserObserver` gỡ liên kết ngay — người đã nghỉ việc không được nhận thông báo nội bộ |
| Người dùng tự hủy | `DELETE /api/users/me/telegram` xoá sạch chat_id và token |

Lỗi nội dung (HTML sai, tin quá dài) **không** tính là lỗi vĩnh viễn — một tin dựng hỏng
không được phép làm mất liên kết của người dùng.

## 5. Chốt chặn trước khi một thông báo được gửi qua Telegram

1. `tg_enabled` đang bật và có `tg_bot_token`.
2. Sự kiện đó đang bật trong cấu hình thông báo (`notification_event_configs.enabled`).
3. Kênh `telegram` nằm trong danh sách kênh của lịch tương ứng (tức thì hoặc mốc nhắc).
4. Người nhận đã liên kết (`telegram_chat_id` khác rỗng) — chưa liên kết thì delivery ghi `skipped`, không lỗi.
