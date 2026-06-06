# Auth — SSO (Đà Nẵng / CBCCVC)

## Tổng quan

SSO dùng để xác thực người dùng qua cổng bên ngoài. **Không tạo user mới** — chỉ xác thực nếu user đã có trong hệ thống.

Có 2 provider:

| Provider | Endpoint | Cách xác thực |
|---|---|---|
| `sso_danang` | `POST /api/sso/exchange` | OAuth2 authorization code |
| `cbccvc` | `POST /api/sso/cbccvc/login` | Username/password |

---

## 1. POST /api/sso/exchange (SSO Đà Nẵng)

Luồng: FE redirect user sang SSO Gateway lấy `code` → gửi `code` lên API này.

### Request

```json
{
  "provider": "sso_danang",
  "code": "abc123..."
}
```

### Response (thành công)

```json
{
  "success": true,
  "message": "Đăng nhập thành công.",
  "data": {
    "access_token": "1|xxx...",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "Admin", "email": "admin@example.com" },
    "available_organizations": [{ "id": 1, "name": "Sở Nội vụ" }],
    "current_organization_id": 1,
    "roles": ["super_admin"],
    "permissions": ["users.index", "..."],
    "abilities": [{"action": "index", "subject": "User"}]
  }
}
```

### Response (không tìm thấy user local)

```json
{
  "success": false,
  "message": "Không tìm thấy tài khoản local khớp với tài khoản SSO.",
  "code": "UNAUTHENTICATED"
}
```
Status: **401**

### Response (tài khoản bị khóa)

```json
{
  "success": false,
  "message": "Tài khoản của bạn đã bị khóa",
  "code": "FORBIDDEN"
}
```
Status: **403**

### Response (code hết hạn/sai)

```json
{
  "success": false,
  "message": "Mã xác thực không hợp lệ hoặc đã hết hạn. Vui lòng thử lại.",
  "code": "VALIDATION_ERROR"
}
```
Status: **400**

---

## 2. POST /api/sso/cbccvc/login (CBCCVC)

Đăng nhập bằng username/password CBCCVC.

### Request

```json
{
  "username": "giangpt",
  "password": "123456"
}
```

### Response

Giống hệt `/sso/exchange` — thành công trả `access_token`, thất bại trả lỗi tương ứng.

---

## Cách hệ thống match user

API SSO nhận userinfo từ provider (email, username), sau đó tìm user local:

```
1. Match users.email = email từ provider
   → nếu có → login với user đó
2. Match users.user_name = username từ provider
   → nếu có → login với user đó
3. Không match → 401
```

| Provider | `email` từ đâu? | `username` từ đâu? |
|---|---|---|
| `sso_danang` | `userinfo.email` (OAuth2) | `userinfo.preferred_username` fallback `userinfo.sub` |
| `cbccvc` | CBCCVC response `user.email` | Input `username` user gõ trên form |

**User phải có sẵn trong hệ thống** (được admin tạo qua `POST /api/users` hoặc import). SSO không tự động tạo user.

---

## Khác so với phiên bản cũ

| | Cũ | Mới |
|---|---|---|
| Tự động tạo user | Có — nếu email chưa có trong DB thì tạo user mới | Không — chỉ xác thực user đã có |
| Lưu social link | Có — bảng `user_socials` lưu provider + provider_user_id | Bỏ hoàn toàn |
| Match user | Query `user_socials` trước, fallback `users.email` | Query thẳng `users.email` trước, fallback `users.user_name` |
| Gán role tự động | Có — setting `auth_auto_create_default_role_id` | Không dùng nữa |

---

## Lưu ý FE

- **Code SSO hết hạn nhanh** — FE nên gọi `/sso/exchange` ngay sau khi redirect về từ SSO Gateway
- **401 trả về khi chưa có user** — FE cần hiển thị thông báo "Tài khoản chưa được cấp quyền truy cập" hoặc tương tự
- Response format giữ nguyên `{ success, message, data, code }` — không đổi
