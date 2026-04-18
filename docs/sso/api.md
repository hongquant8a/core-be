# SSO Integration API

Tài liệu API cho FE tích hợp đăng nhập qua **SSO Đà Nẵng** và **CBCCVC**, bổ sung bên cạnh login password truyền thống.

- **Base URL:** `{APP_URL}/api/auth/sso`
- **Auth:** `@unauthenticated` (không cần Bearer token)
- **Content-Type:** `application/json`
- **Response envelope:** thống nhất `{ "success": bool, "message": string, "data": {...} }` — giống endpoint `/api/auth/login`.

## 1. Settings cần cấu hình trước

Admin phải cấu hình qua endpoint settings (hoặc trực tiếp DB) trước khi FE render nút đăng nhập.

### SSO Đà Nẵng

| Key | Type | Bắt buộc khi enabled | Mô tả |
|---|---|---|---|
| `sso_danang_enabled` | boolean | — | Bật/tắt. FE đọc từ `GET /api/settings/public` để render nút. |
| `sso_danang_base_url` | string | ✓ | Base URL SSO Gateway, vd `https://sso.danang.gov.vn` (prod) hoặc `https://ssoqa.danang.gov.vn` (QA). |
| `sso_danang_client_id` | string | ✓ | Client ID do SSO Gateway cấp. |
| `sso_danang_client_secret` | string | ✓ | **Secret. is_public=false, không expose ra FE.** |
| `sso_danang_redirect_uri` | string | ✓ | URL trang callback của SPA, phải trùng với đăng ký ở SSO Gateway. Vd `https://qlcv.danang.gov.vn/auth/sso/danang/callback`. |
| `sso_danang_scope` | string | ✓ | Mặc định `openid profile email`. **Phải có `openid`**. |

### CBCCVC

| Key | Type | Bắt buộc khi enabled | Mô tả |
|---|---|---|---|
| `sso_cbccvc_enabled` | boolean | — | Bật/tắt. |
| `sso_cbccvc_base_url` | string | ✓ | Base URL CBCCVC, vd `https://cbccvc.danang.gov.vn`. |

### Auth chung

| Key | Type | Mô tả |
|---|---|---|
| `auth_auto_create_default_role_id` | integer | Role mặc định khi tạo user mới qua SSO (bất kỳ provider). Nếu null → user không có role, admin gán sau. **is_public=false.** |

## 2. Endpoints

### 2.1. `POST /api/auth/sso/exchange` — OAuth code exchange

Dùng cho **SSO Đà Nẵng** (và OAuth provider tương lai như Google, Facebook). SPA gọi sau khi nhận được `code` từ redirect callback.

**Request:**
```json
{
  "provider": "sso_danang",
  "code": "<authorization_code>"
}
```

**Field:**
- `provider` (required, string) — hiện chỉ chấp nhận `sso_danang`. Giá trị khác → 422.
- `code` (required, string) — authorization code nhận được từ callback URL.

**Response 200 — thành công (shape giống hệt `/api/auth/login`):**
```json
{
  "success": true,
  "message": "Đăng nhập thành công.",
  "data": {
    "access_token": "1|xxx...",
    "token_type": "Bearer",
    "user": { "id": 42, "name": "Phan Tấn Giang", "email": "giangpt@danang.gov.vn" },
    "available_organizations": [{ "id": 2, "name": "Sở Nội vụ", "description": null }],
    "current_organization_id": 2,
    "roles": ["admin"],
    "permissions": ["users.index", "users.store"],
    "abilities": [{ "action": "index", "subject": "User" }]
  }
}
```

**Lỗi (generic message — chi tiết full error được log server-side):**
| HTTP | Body message | Nguyên nhân |
|---|---|---|
| 422 | (validation errors) | `provider` không thuộc whitelist (`sso_danang`) hoặc thiếu `code`. |
| 404 | "Chức năng chưa được kích hoạt." | Provider chưa enabled (`sso_danang_enabled=false`). |
| 400 | "Mã xác thực không hợp lệ hoặc đã hết hạn. Vui lòng thử lại." | Code invalid/expired ở upstream (invalid_grant). |
| 502 | "Cổng đăng nhập không phản hồi. Vui lòng thử lại sau." | SSO Gateway lỗi/timeout. |
| 403 | "Tài khoản của bạn đã bị khóa" | User `status != 'active'` sau sync. |

### 2.2. `POST /api/auth/sso/cbccvc/login` — CBCCVC direct login

Dùng cho CBCCVC — FE hiển thị form username/password của mình, gửi credentials sang endpoint này. **Không có redirect.**

**Request:**
```json
{
  "username": "giangpt",
  "password": "••••••"
}
```

**Field:**
- `username` (required, string)
- `password` (required, string)

**Response 200 — thành công:** shape giống hệt `/api/auth/sso/exchange` ở mục 2.1 (có đầy đủ `available_organizations`, `roles`, `permissions`, `abilities`).

**Lỗi:**
| HTTP | Body message | Nguyên nhân |
|---|---|---|
| 422 | (validation errors) | Thiếu `username` hoặc `password`. |
| 404 | "Chức năng chưa được kích hoạt." | CBCCVC chưa enabled. |
| 401 | "Tài khoản hoặc mật khẩu không đúng." | Sai credentials. |
| 502 | "Cổng đăng nhập không phản hồi. Vui lòng thử lại sau." | CBCCVC lỗi/timeout. |
| 403 | "Tài khoản của bạn đã bị khóa" | User `status != 'active'` sau sync. |

## 3. Integration flow — SSO Đà Nẵng

Áp dụng **Flow Y** (SPA handles redirect, backend chỉ exchange). Redirect URI trỏ về trang SPA, không phải backend.

```
SPA                                                       Backend
 │
 │  [User clicks "Đăng nhập SSO Đà Nẵng"]
 │
 │  1. GET /api/settings/public
 │     → lấy sso_danang_{base_url, client_id, redirect_uri, scope}
 │
 │  2. const state = crypto.randomUUID()
 │     sessionStorage.setItem('sso_danang_state', state)
 │
 │  3. window.location.href =
 │       `${base_url}/oauth2/authorize`
 │       + `?response_type=code`
 │       + `&client_id=${client_id}`
 │       + `&redirect_uri=${encodeURIComponent(redirect_uri)}`
 │       + `&scope=${encodeURIComponent(scope)}`
 │       + `&state=${state}`
 │
 ▼
[sso.danang.gov.vn/oauth2/authorize] — user chọn VNeID/email công vụ, đăng nhập
 │
 │  302 → {redirect_uri}?code=<code>&state=<state>&session_state=...
 │
 ▼
SPA (at /auth/sso/danang/callback)
 │
 │  4. Parse URL query: code, state
 │  5. Verify state === sessionStorage.getItem('sso_danang_state')
 │     (CSRF protection)
 │  6. sessionStorage.removeItem('sso_danang_state')
 │
 │  7. POST /api/auth/sso/exchange
 │       Body: { provider: 'sso_danang', code }
 │     ─────────────────────────────────────────────────────►
 │                                                  8. POST token + GET userinfo
 │                                                  9. UserSyncService.syncFromUserinfo
 │                                                 10. $user->createToken(...)
 │     ◄─────────────────────────────────────────────────────
 │  11. { success, data: { access_token, user } }
 │
 │  12. localStorage.setItem('access_token', access_token)
 │      navigate('/')
```

**Lưu ý quan trọng:**
- `state` verify **phải làm ở FE** (sessionStorage), không có backend cache — nếu không match thì abort, không gọi exchange.
- `redirect_uri` FE dùng khi build authorize URL **phải giống hệt** giá trị trong setting `sso_danang_redirect_uri` (SSO Gateway check exact match).
- `scope` bắt buộc có `openid`, nhiều scope cách nhau bằng dấu cách.
- Nếu SSO callback có `error=...` trong query (user từ chối, v.v.), FE hiển thị lỗi, không gọi `/exchange`.

## 4. Integration flow — CBCCVC

```
SPA                                                       Backend
 │
 │  [User clicks "Đăng nhập CBCCVC" → hiện form]
 │
 │  1. POST /api/auth/sso/cbccvc/login
 │       Body: { username, password }
 │     ─────────────────────────────────────────────────────►
 │                                      2. POST {cbccvc}/index.php?...&task=login
 │                                         Body: { username, password }
 │                                         → 200 { data: { user, jwt } } hoặc 401
 │                                      3. Extract userinfo từ response.data.user
 │                                      4. UserSyncService.syncFromUserinfo('cbccvc', ...)
 │                                      5. createToken
 │     ◄─────────────────────────────────────────────────────
 │  6. { success, data: { access_token, user } }
 │
 │  7. localStorage.setItem('access_token', access_token)
```

Không có redirect. Form do FE tự quản lý.

## 5. Sync logic (server-side)

Với mỗi `userinfo` nhận được từ provider, server chạy:

```
1. Tìm user_socials theo (provider, provider_user_id).
   - Có → load user đã link, refresh provider_data (audit), trả về.
2. Không có → tìm users theo email.
   - Có user local cùng email → link social vào user đó. KHÔNG ghi đè
     name/password/... của user local.
   - Không có → tạo user mới: email, name, user_name=null,
     password=random (không dùng), status='active'.
     Nếu auth_auto_create_default_role_id có giá trị → gán role đó.
3. Tạo record user_socials (nếu chưa có).
```

**Hệ quả:**
- User login qua SSO được quản lý y như user thường (role, permission, organization access).
- 1 user có thể link nhiều provider đồng thời (1-N qua `user_socials`).
- Sau khi link, SSO **không overwrite** field nào của `users` — admin sửa tay nếu cần.

## 6. Logout

SSO integration **không có SSO-side logout** trong v1. FE gọi endpoint sẵn có:

```
POST /api/auth/logout
Authorization: Bearer {access_token}
```

→ Chỉ xóa Sanctum token ở app. Phiên SSO ở provider tự expire.

## 7. Environments

| | Prod | QA |
|---|---|---|
| App API | `https://qlcv.danang.gov.vn/api` | `https://qlcv-qa.danang.gov.vn/api` |
| SSO Đà Nẵng base | `https://sso.danang.gov.vn` | `https://ssoqa.danang.gov.vn` |
| CBCCVC base | `https://cbccvc.danang.gov.vn` | (dùng chung) |

Trị `sso_danang_redirect_uri` phải là URL của SPA môi trường tương ứng.

## 8. Related documents

- **Design spec:** [../superpowers/specs/2026-04-18-sso-integration-design.md](../superpowers/specs/2026-04-18-sso-integration-design.md)
- **Implementation plan:** [../superpowers/plans/2026-04-18-sso-integration.md](../superpowers/plans/2026-04-18-sso-integration.md)
- **SSO Gateway raw docs:** [1.txt](1.txt), [2.txt](2.txt)
- **CBCCVC raw docs:** [3.txt](3.txt)
- **Scribe auto-generated:** `{APP_URL}/docs` (sau khi chạy `php artisan scribe:generate`).
