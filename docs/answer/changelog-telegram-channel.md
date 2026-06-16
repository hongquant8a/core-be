# Telegram Notification Channel — Hướng dẫn FE

## 1. Tổng quan

Kênh Telegram gửi thông báo đến **từng người dùng** qua Telegram Bot API. Tin nhắn dùng `parse_mode=HTML` (hỗ trợ `<b>in đậm</b>`, `\n` xuống dòng).

**Luồng:**
1. Admin cấu hình bot token trong Settings
2. User liên kết tài khoản Telegram (lấy `chat_id`)
3. Khi có sự kiện (giao việc, nhắc hạn, họp, lịch...), hệ thống gửi Telegram nếu user đã liên kết và kênh được bật

## 2. Admin: Cấu hình Settings (page Settings chung)

| Key | Group | Type | Label |
|---|---|---|---|
| `tg_enabled` | `telegram` | boolean | Bật Telegram |
| `tg_bot_token` | `telegram` | string | Bot Token |

**API lấy config:** `GET /api/settings/public?groups=telegram` (public endpoint, không cần auth)
**API lưu config:** `POST /api/settings` (admin, yêu cầu auth + quyền)

## 3. User: Liên kết Telegram

### 3.1. Cách lấy `telegram_chat_id`

Có 2 cách:

**Cách A — Manual (đơn giản):**
- FE hiển thị input field để user nhập `telegram_chat_id` thủ công
- User tự tìm chat_id của mình (vào Telegram → search `@userinfobot` → gửi `/start` → copy `Id`)
- FE gọi `PATCH /api/users/{id}` với `{ "telegram_chat_id": "123456789" }`

**Cách B — Deep link (UX tốt hơn, bot tự lấy):**
- Bot Telegram có webhook trỏ về BE (`POST /api/telegram/webhook`)
- Bot nhận message từ user → gửi `chat_id` về BE webhook → BE match user (qua token/deep-link param) → tự lưu vào `user_profiles.telegram_chat_id`
- Cần BE implement thêm webhook endpoint. Nếu cần, yêu cầu BE bổ sung.

Hiện tại BE hỗ trợ Cách A (manual). FE tạo 1 field trong page User Profile để nhập và lưu.

### 3.2. API lưu `telegram_chat_id`

```
PATCH /api/users/{user_id}
Body: { "telegram_chat_id": "123456789" }
```

- Nếu muốn hủy liên kết: `PATCH /api/users/{user_id}` với `{ "telegram_chat_id": null }`
- Response trả UserResource có field `telegram_chat_id`

### 3.3. Response mẫu (UserResource)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Nguyễn Văn A",
    "telegram_chat_id": "123456789",
    ...
  }
}
```

## 4. FE Pages cần làm

### 4.1. Admin Settings → group "telegram"

Thêm group `telegram` trong page Settings với 2 field:
- **Bật Telegram** — switch/toggle (`tg_enabled`, type `boolean`)
- **Bot Token** — text input ẩn (`tg_bot_token`, type `string`)

Pattern giống hệt group `zalo` hoặc `sms` hiện có.

### 4.2. User Profile → liên kết Telegram

Trong page User Profile (hoặc page "Kênh thông báo của tôi"), thêm section:
- **Trạng thái:** Hiển thị "Đã liên kết: `{telegram_chat_id}`" hoặc "Chưa liên kết"
- **Nút "Liên kết Telegram":** Mở input nhập chat_id → gọi `PATCH /api/users/{id}`
- **Nút "Hủy liên kết":** Gọi `PATCH /api/users/{id}` với `telegram_chat_id: null`
- **Link "Lấy Chat ID":** Mở `https://t.me/userinfobot` trong tab mới

## 5. Nội dung tin nhắn Telegram (do BE tạo, FE không cần làm)

Mỗi loại sự kiện có format riêng, dùng HTML. Ví dụ:

| Sự kiện | Nội dung mẫu |
|---|---|
| Giao việc | `<b>Bạn vừa được giao công việc</b>\n\nTên việc (hạn 01/01/2026).` |
| Nhắc hạn | `<b>Nhắc công việc sắp đến hạn</b>\n\nSắp đến hạn công việc: Tên việc.` |
| Mời họp | `<b>Bạn được mời tham dự cuộc họp</b>\n\nTiêu đề\nThời gian: 14:00 01/01/2026\nXem chi tiết: url` |
| Hủy họp | `<b>Cuộc họp đã bị hủy</b>\n\nTiêu đề\nXem chi tiết: url` |
| Lịch công tác | `<b>Lịch công tác mới được ban hành</b>\n\nNội dung\nNgày: 01/01/2026\nXem chi tiết: url` |
