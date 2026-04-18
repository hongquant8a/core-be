# SSO Integration Design — SSO Đà Nẵng + CBCCVC

**Date:** 2026-04-18
**Status:** Approved, ready for implementation plan
**Scope:** Tích hợp 2 cổng đăng nhập thứ cấp bên cạnh login password hiện có, kiến trúc mở rộng cho nhiều provider trong tương lai (Google, Facebook...).

## 1. Overview

Hệ thống hiện có 1 phương thức đăng nhập duy nhất (username/password qua module `Auth` với Sanctum). Design này thêm:

- **SSO Đà Nẵng** — cổng xác thực tập trung của thành phố (OAuth2/OIDC), phía sau có VNeID / email công vụ. App chỉ tích hợp với SSO Gateway, không cần biết provider nguồn.
- **CBCCVC** — hệ CSDL cán bộ công chức của thành phố, API username/password trực tiếp.

Cả 2 đều **enabled/disabled** bằng settings. Khi enabled, trang login SPA hiển thị thêm nút tương ứng. User đăng nhập thành công qua SSO sẽ được **map vào user local như user thường** — phân role/quyền, quản lý hoàn toàn bình thường.

## 2. Scope & Non-goals

### In scope
- Bảng `user_socials` 1-N với `users`, multi-provider (1 user có thể link nhiều provider đồng thời).
- Settings cho SSO Đà Nẵng và CBCCVC.
- 2 endpoints: OAuth exchange (đa provider) + CBCCVC login (trực tiếp).
- Sync user từ userinfo (create nếu mới, link nếu email đã tồn tại).
- Auto-create user với role default (cấu hình được).
- Response shape thống nhất với login hiện có (`{token, user}`).

### Non-goals (v1)
- **SSO-side logout** (gọi `/oidc/logout` của SSO Đà Nẵng). Phiên SSO tự expire bên provider.
- **Sync email/name từ SSO về user local** sau lần login đầu tiên. User fields ổn định, chỉ thay đổi khi admin sửa thủ công.
- **PKCE / server-side state cache**. State verification làm FE-side (sessionStorage) — đủ cho SPA internal.
- **Account linking UI** (user tự link/unlink social account). Link được tạo tự động khi email match.
- **Refresh token flow**. Chỉ lấy access_token đủ để gọi userinfo.
- **Unified OAuth provider abstraction** với Google/Facebook. Interface sẵn sàng mở rộng nhưng chỉ implement Đà Nẵng ở v1.

## 3. Data Model

### Bảng mới: `user_socials`

```
user_socials
├── id                 BIGINT PK
├── user_id            BIGINT FK users.id  ON DELETE CASCADE  (index)
├── provider           VARCHAR   ('sso_danang' | 'cbccvc' | future: 'google', 'facebook')
├── provider_user_id   VARCHAR   (sub từ SSO Đà Nẵng / user id từ CBCCVC, trim khoảng trắng)
├── provider_data      JSON NULL (raw userinfo — audit/debug)
├── linked_at          TIMESTAMP
├── created_at, updated_at
├── UNIQUE (provider, provider_user_id)
└── UNIQUE (user_id, provider)
```

**Constraints (1-N — 1 user có nhiều social):**
- `(provider, provider_user_id) UNIQUE` → 1 social account chỉ thuộc về 1 user.
- `(user_id, provider) UNIQUE` → 1 user không link 2 account cùng provider. (Muốn đổi tài khoản SSO Đà Nẵng khác → phải unlink cái cũ trước.)
- `ON DELETE CASCADE` từ `users` → xóa user thì social records mất theo.

### Thay đổi bảng `users`
**Không có migration mới cho `users`.** Schema hiện tại đủ:
- `email` — đã unique (từ migration gốc).
- `user_name` — đã nullable + unique.
- `status` — default `'active'`.

### Models

**`App\Modules\Core\Models\UserSocial` (mới):**
```php
class UserSocial extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'provider_data', 'linked_at'];
    protected $casts = ['provider_data' => 'array', 'linked_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
```

**`User` model — bổ sung:**
```php
public function socials() { return $this->hasMany(UserSocial::class); }
```

## 4. Settings

Bổ sung vào `database/seeders/SettingSeeder.php`:

### Group `sso_danang`
| Key | Type | Default | is_public | Label |
|---|---|---|---|---|
| `sso_danang_enabled` | boolean | `'0'` | **true** | Bật SSO Đà Nẵng |
| `sso_danang_base_url` | string | `https://sso.danang.gov.vn` | true | Base URL |
| `sso_danang_client_id` | string | `null` | true | Client ID |
| `sso_danang_client_secret` | string | `null` | **false** | Client Secret |
| `sso_danang_redirect_uri` | string | `null` | true | Redirect URI (FE callback) |
| `sso_danang_scope` | string | `openid profile email` | true | Scope |

### Group `sso_cbccvc`
| Key | Type | Default | is_public | Label |
|---|---|---|---|---|
| `sso_cbccvc_enabled` | boolean | `'0'` | **true** | Bật CBCCVC |
| `sso_cbccvc_base_url` | string | `https://cbccvc.danang.gov.vn` | true | Base URL |

### Group `auth`
| Key | Type | Default | is_public | Label |
|---|---|---|---|---|
| `auth_auto_create_default_role_id` | integer | `null` | false | Role mặc định khi tạo user qua SSO |

**Ghi chú:**
- `is_public = true` cho các key cần để FE render nút login và build authorize URL (client_id, base_url, redirect_uri, scope).
- `client_secret` và `auth_auto_create_default_role_id` **phải** được filter khỏi public settings endpoint.
- Paths API **hardcode** trong service:
  - Đà Nẵng: `/oauth2/authorize`, `/oauth2/token`, `/oauth2/userinfo`, `/oidc/logout`
  - CBCCVC: `/index.php?option=com_api&controller=core&task=login`

## 5. Endpoints & Flow

### 5.1 Backend routes

Thêm vào `app/Modules/Auth/Routes/auth.php`:

```php
Route::prefix('sso')->group(function () {
    Route::post('exchange',     [SsoController::class, 'exchange']);      // OAuth code exchange (multi-provider)
    Route::post('cbccvc/login', [SsoController::class, 'cbccvcLogin']);  // CBCCVC direct login
});
```

### 5.2 Endpoint contracts

**POST `/api/auth/sso/exchange`** — OAuth code exchange
```json
Request:  { "provider": "sso_danang", "code": "<authorization_code>" }
Response: { "token": "<sanctum_token>", "user": { ...UserResource... } }
```

**POST `/api/auth/sso/cbccvc/login`** — CBCCVC direct login
```json
Request:  { "username": "...", "password": "..." }
Response: { "token": "<sanctum_token>", "user": { ...UserResource... } }
```

Response shape **giống hệt** `/api/auth/login` hiện có để FE reuse logic.

### 5.3 Frontend routes

- `/login` — page hiện có, đọc public settings để render thêm:
  - Button **"Đăng nhập SSO Đà Nẵng"** khi `sso_danang_enabled=true`.
  - Tab/form **"Đăng nhập CBCCVC"** khi `sso_cbccvc_enabled=true`.
- `/auth/sso/danang/callback` — page mới, xử lý callback từ SSO Đà Nẵng.

### 5.4 Flow SSO Đà Nẵng (OAuth2 — Flow Y: SPA handles redirect)

```
SPA                                                              Backend
 │ 1. User click "SSO Đà Nẵng"
 │ 2. Generate state = uuid(), save sessionStorage
 │ 3. Build URL: {base}/oauth2/authorize?response_type=code
 │       &client_id={id}&redirect_uri={uri}
 │       &scope=openid profile email&state={state}
 │ 4. window.location = URL
 │
 ▼
[sso.danang.gov.vn] ← user login tại đây
 │
 │ 302 → {redirect_uri}?code=xyz&state={state}
 ▼
SPA (at /auth/sso/danang/callback)
 │ 5. Parse code + state, verify state khớp sessionStorage
 │ 6. POST /api/auth/sso/exchange {provider:'sso_danang', code}
 │ ──────────────────────────────────────────────────────────────►
 │                       7. POST {base}/oauth2/token
 │                          {grant_type=authorization_code, code,
 │                           redirect_uri, client_id, client_secret}
 │                          → access_token + id_token
 │                       8. GET  {base}/oauth2/userinfo
 │                          Authorization: Bearer access_token
 │                          → {email, name, sub, upn}
 │                       9. UserSyncService::syncFromUserinfo(...)
 │                      10. Issue Sanctum token
 │ ◄──────────────────────────────────────────────────────────────
 │ 11. Lưu token, redirect /dashboard
```

**State verification:** FE-side only (sessionStorage). Đủ CSRF protection cho SPA internal. Không cần backend cache state.

### 5.5 Flow CBCCVC (Direct API)

```
SPA                                                              Backend
 │ 1. User click "CBCCVC" → hiện form username/password
 │ 2. POST /api/auth/sso/cbccvc/login {username, password}
 │ ──────────────────────────────────────────────────────────────►
 │                       3. POST {base}/index.php?option=com_api
 │                          &controller=core&task=login
 │                          {username, password}
 │                          → 200 {user, jwt} hoặc 401
 │                       4. Extract userinfo từ response.user (payload TBD)
 │                       5. UserSyncService::syncFromUserinfo(...)
 │                       6. Issue Sanctum token
 │ ◄──────────────────────────────────────────────────────────────
 │ 7. Lưu token, redirect /dashboard
```

**CBCCVC JWT:** **bỏ qua** — không lưu, không dùng. App dùng Sanctum token riêng. (Nếu v2 cần gọi API CBCCVC thay user, có thể lưu JWT trong `user_socials.provider_data`.)

**Payload userinfo CBCCVC:** sẽ được cung cấp khi implement — user đồng ý gửi sau.

## 6. User Sync Logic

Pseudocode chung cho cả 2 provider (chạy trong `UserSyncService`):

```php
public function syncFromUserinfo(string $provider, array $userinfo): User
{
    $providerUserId = trim($userinfo['sub']);
    $email = trim($userinfo['email']);
    $name  = trim($userinfo['name']);

    // 1. Match theo user_socials (đã từng login qua provider này)
    $social = UserSocial::firstWhere([
        'provider' => $provider,
        'provider_user_id' => $providerUserId,
    ]);

    if ($social) {
        $social->update(['provider_data' => $userinfo]);  // refresh audit data
        return $social->user;                              // KHÔNG sync field users
    }

    // 2. Fallback: match theo email (user local đã có, chưa từng login SSO)
    $user = User::where('email', $email)->first();

    if (!$user) {
        // 3. Tạo user mới
        $user = User::create([
            'email'    => $email,
            'name'     => $name,
            'user_name'=> null,
            'password' => Hash::make(Str::random(32)),
            'status'   => 'active',
        ]);

        if ($roleId = Setting::get('auth_auto_create_default_role_id')) {
            $user->assignRole($roleId);
        }
    }
    // Nếu user đã tồn tại (email match): không update user fields, chỉ link social mới.
    // Với 1-N: user có thể có nhiều social (mỗi provider khác nhau) — unique(user_id, provider)
    // đảm bảo không link 2 account cùng provider vào 1 user.

    // Tạo social link (cả create path và link path)
    UserSocial::create([
        'user_id'          => $user->id,
        'provider'         => $provider,
        'provider_user_id' => $providerUserId,
        'provider_data'    => $userinfo,
        'linked_at'        => now(),
    ]);

    return $user;
}
```

**Nguyên tắc:**
- User fields trong `users` **ổn định** sau initial create/link — không bị SSO ghi đè.
- `provider_data` trong `user_socials` **được refresh** mỗi lần login (audit trail).
- Đảm bảo check `users.email UNIQUE` — tránh race condition tạo user trùng email (dùng DB transaction + lock nếu cần).

## 7. Error Handling

| Tình huống | HTTP | Message |
|---|---|---|
| SSO Đà Nẵng `/token` fail (code invalid/expired) | 400 | "Không thể đổi mã xác thực. Vui lòng thử lại." |
| SSO Đà Nẵng `/userinfo` fail | 502 | "SSO Gateway không phản hồi." |
| CBCCVC login sai credentials | 401 | "Tài khoản hoặc mật khẩu không đúng." |
| CBCCVC timeout/500 | 502 | "CBCCVC không phản hồi." |
| User `status != 'active'` sau sync | 403 | "Tài khoản bị khóa." |
| Provider chưa enabled trong settings | 404 | "Chức năng chưa được kích hoạt." |
| Missing required setting (client_id, base_url…) | 500 | "Cấu hình SSO không đầy đủ." |
| Provider không hợp lệ (whitelist fail) | 422 | "Provider không hợp lệ." |

Tất cả lỗi log lại kèm `provider`, `provider_user_id` (nếu có) để debug.

## 8. File Layout

```
app/Modules/Auth/
├── Controllers/
│   └── SsoController.php              ← MỚI (actions: exchange, cbccvcLogin)
├── Services/
│   ├── AuthService.php                ← giữ nguyên
│   ├── UserSyncService.php            ← MỚI (logic Section 6)
│   └── Providers/
│       ├── SsoProvider.php            ← MỚI (interface)
│       ├── SsoDanangProvider.php      ← MỚI (exchange code → userinfo)
│       └── CbccvcProvider.php         ← MỚI (login → userinfo)
├── Requests/
│   ├── SsoExchangeRequest.php         ← MỚI
│   └── CbccvcLoginRequest.php         ← MỚI
└── Routes/
    └── auth.php                       ← thêm 2 route

app/Modules/Core/
└── Models/
    ├── User.php                       ← thêm hasOne(UserSocial)
    └── UserSocial.php                 ← MỚI

database/
├── migrations/
│   └── 2026_04_18_XXXXXX_create_user_socials_table.php    ← MỚI
└── seeders/
    └── SettingSeeder.php              ← thêm 3 group: sso_danang, sso_cbccvc, auth
```

### Provider interface

```php
namespace App\Modules\Auth\Services\Providers;

interface SsoProvider
{
    /**
     * Nhận payload từ client, gọi ra provider, trả về userinfo chuẩn hóa.
     *
     * @param array $payload  ['code' => ...] hoặc ['username' => ..., 'password' => ...]
     * @return array  ['email', 'name', 'sub', '...raw provider fields']
     */
    public function getUserinfo(array $payload): array;
}
```

Cho phép thêm provider mới (Google, FB) bằng cách:
1. Implement `SsoProvider`.
2. Register vào `SsoController` whitelist.
3. Thêm settings cho provider đó.

## 9. Open Questions / TBD

| # | Topic | Trạng thái |
|---|---|---|
| 1 | Payload userinfo của CBCCVC (response từ `/login` API) | User sẽ cung cấp khi implement |
| 2 | Mapping fields CBCCVC → `{email, name, sub}` chuẩn hóa | Phụ thuộc #1 |
| 3 | UI admin để cấu hình các setting group mới | Coi như dùng UI settings chung hiện có — nếu cần custom sẽ làm sau |

## 10. Future Extensions (v2+)

- SSO logout full (gọi `/oidc/logout` với `id_token_hint`).
- Thêm provider Google/Facebook (với `email_verified` check cho Google, cân nhắc email match policy cho Facebook).
- Account linking UI (user tự link/unlink social account).
- Per-provider `auto_create_default_role_id` (thay vì 1 setting chung).
- Batch sync job — sync email/name từ provider về user local theo schedule.
- Rate limiting riêng cho endpoint SSO.
