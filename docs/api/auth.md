# API Xác thực (Auth)

> Cập nhật lần cuối: 15/07/2026 — bổ sung endpoint `request-account`, bổ sung field còn thiếu trong response `/api/user` (me), ghi chú header `X-Device-Id` khi logout.

Đăng nhập, đăng xuất, quên mật khẩu, đặt lại mật khẩu, chuyển tổ chức làm việc, gửi yêu cầu mở tài khoản (guest). Response đăng nhập trả về user, danh sách organization, **roles** và **permissions** (theo tổ chức hiện tại khi đã xác định được) để Vue Casl lưu và sử dụng. Tổ chức gần nhất được lưu trong bảng **`user_preferences`**; `POST /api/auth/switch-organization` cập nhật bản ghi này.

**Base path:** `/api/auth`

---

## Đăng nhập

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/auth/login` |
| **Body** | `email` (required, email hoặc user_name), `password` (required). |
| **Response 200** | `{ "access_token": "...", "token_type": "Bearer", "user": {...}, "available_organizations": [...], "current_organization_id": 2 hoặc null, "roles": [...], "permissions": [...], "abilities": [...] }`. `current_organization_id`: từ `user_preferences` nếu còn hợp lệ; nếu user chỉ có **một** tổ chức thì tự gán và lưu; nếu có **nhiều** tổ chức và chưa có preference hợp lệ thì **null** (cần chọn tổ chức rồi gọi switch-organization). Khi `null`, `roles` / `permissions` / `abilities` thường rỗng. |
| **Response 401** | `{ "message": "Thông tin đăng nhập không chính xác" }`. |
| **Response 403** | `{ "message": "Tài khoản của bạn đã bị khóa" }`. |

**Lưu ý:** `roles`, `permissions` và `abilities` theo tổ chức đã xác định (`current_organization_id`). `abilities` theo chuẩn CASL: mỗi permission Laravel = 1 object `{ "action", "subject" }` (action giữ nguyên: index, show, store, ...), dùng cho Vue Casl.

---

## Lấy thông tin user hiện tại (me)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/user` |
| **Header** | `Authorization: Bearer {access_token}` (required), `X-Organization-Id` (required). |
| **Response 200** | `{ "success": true, "data": { "user": {...}, "current_organization_id": 2, "available_organizations": [...], "roles": ["admin"], "permissions": ["users.index", ...], "abilities": [{ "action": "read", "subject": "User" }, ...] } }`. |

Dùng để Vue Casl khởi tạo ability khi refresh trang. Cần header `X-Organization-Id`. Response gồm thêm `current_organization_id`/`available_organizations` (giống response `login`) để FE không cần gọi API riêng khi refresh trang.

---

## Đăng xuất

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/auth/logout` |
| **Header** | `Authorization: Bearer {access_token}` (required). `X-Device-Id` (optional) — nếu gửi, chỉ xóa FCM token của đúng thiết bị đó; không gửi sẽ xóa toàn bộ FCM token của user (đăng xuất khỏi mọi thiết bị push). |
| **Response** | `{ "message": "Đã đăng xuất" }`. |

---

## Gửi yêu cầu mở tài khoản (guest)

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/auth/request-account` |
| **Auth** | Không cần (public, throttle 5 lần/10 phút). |
| **Body** | `full_name` (required, max 150), `phone` (required, max 20), `email` (required, email, max 150), `content` (required, max 2000). |
| **Response 200** | `{ "message": "Yêu cầu đã được gửi." }` — hệ thống forward nội dung tới email liên hệ cấu hình sẵn (`contact_email` setting). |
| **Response 503** | `{ "message": "Hệ thống chưa cấu hình email tiếp nhận. Vui lòng liên hệ quản trị." }` — khi chưa cấu hình email nhận. |

---

## Chuyển tổ chức làm việc

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/auth/switch-organization` |
| **Header** | `Authorization: Bearer {access_token}` (required). |
| **Body** | `organization_id` (required, integer). |
| **Response** | `{ "message": "Đã chuyển tổ chức làm việc.", "data": { "current_organization_id": 2, "current_organization": {...}, "roles": [...], "permissions": [...], "abilities": [{ "action": "read", "subject": "User" }, ...] } }`. Đồng thời lưu `current_organization_id` vào **`user_preferences`** để lần sau đăng nhập nhớ. |

---

## Quên mật khẩu

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/auth/forgot-password` |
| **Body** | `email` (required, email). |
| **Response 200** | `{ "message": "Link reset đã được gửi vào Email" }`. |
| **Response 400** | `{ "message": "Không thể gửi mail" }`. |

---

## Đặt lại mật khẩu

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/auth/reset-password` |
| **Body** | `email` (required), `password` (required, min 6, confirmed), `password_confirmation` (required), `token` (required, từ link trong email reset). |
| **Response 200** | `{ "message": "Mật khẩu đã được đặt lại" }`. |
| **Response 400** | `{ "message": "Không thể đặt lại mật khẩu" }`. |
