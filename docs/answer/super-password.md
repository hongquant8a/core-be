# Auth — Super Password (Mật khẩu hệ thống)

## Khái niệm

Super password là mật khẩu chung do admin cấu hình trong Settings. **Bất kỳ tài khoản nào** cũng có thể dùng super password thay cho mật khẩu cá nhân để đăng nhập.

Ví dụ: admin cấu hình super password = `"Secret@2026"`. Mọi user trong hệ thống đều có thể đăng nhập với:
- Email/user_name của chính họ + mật khẩu cá nhân → OK
- Email/user_name của chính họ + `"Secret@2026"` (super password) → OK

---

## Cách cấu hình (Admin)

`PUT /api/settings`

```json
{
  "system_password": "Secret@2026"
}
```

- Gửi chuỗi rỗng `""` để **xóa** super password (tắt tính năng)
- Key `system_password` nằm trong group `security`, type `password`
- Trong response `GET /api/settings`, giá trị hiển thị là `"••••••"` (đã ẩn)

---

## Luồng đăng nhập

Không có gì thay đổi ở API login.

```
POST /api/login { email: "user@example.com", password: "..." }

BE kiểm tra:
  1. Tìm user theo email/user_name
  2. So password với mật khẩu cá nhân (Hash::check) → khớp → pass
  3. So password với super password (Hash::check) → khớp → pass
  4. Không khớp cả 2 → 401 "Thông tin đăng nhập không chính xác"
```

---

## Response không thay đổi

```json
// Thành công — giống hệt login thường
{
  "success": true,
  "message": "Đăng nhập thành công.",
  "data": {
    "access_token": "1|xxx...",
    "token_type": "Bearer",
    "user": { "id": 1, "name": "Admin", ... },
    "available_organizations": [...],
    "current_organization_id": 1,
    "roles": ["super_admin"],
    "permissions": [...],
    "abilities": [...]
  }
}

// Thất bại — không phân biệt được sai mật khẩu cá nhân hay super password
{
  "success": false,
  "message": "Thông tin đăng nhập không chính xác",
  "code": "UNAUTHENTICATED"
}
```

---

## Lưu ý FE

1. **Không cần thay đổi gì ở form login** — vẫn 2 ô email + password như cũ
2. **Super password nằm ở tầng BE** — FE không cần biết user đang dùng mật khẩu cá nhân hay super password
3. **Khi nhập sai** — vẫn hiển thị "Thông tin đăng nhập không chính xác" như bình thường (bảo mật: không tiết lộ sự tồn tại của super password)
4. **Admin page Settings** — cần thêm 1 ô input trong group "Bảo mật" (key: `system_password`, type: `password`). Khi FE render form settings thì nếu value là `"••••••"` → hiển thị ô rỗng (để user nhập mới hoặc để trống nếu không muốn đổi)
