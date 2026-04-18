# SSO Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tích hợp 2 cổng đăng nhập thứ cấp — SSO Đà Nẵng (OAuth2/OIDC) và CBCCVC (direct API login) — bên cạnh login password hiện có, với kiến trúc mở rộng cho nhiều provider sau này.

**Architecture:** Bảng `user_socials` 1-N với `users` (1 user link nhiều provider). 2 endpoints mới dưới `/api/auth/sso/*`: `exchange` (OAuth multi-provider) và `cbccvc/login` (direct). Core logic trong `UserSyncService` (match theo provider_user_id, fallback email, auto-create với role default). Response shape giống login hiện có (`{access_token, token_type, user, ...}`).

**Tech Stack:** Laravel 12, Sanctum, Spatie Permission, PHPUnit 11, RefreshDatabase trait, Laravel HTTP client (Http facade).

**Spec reference:** [docs/superpowers/specs/2026-04-18-sso-integration-design.md](../specs/2026-04-18-sso-integration-design.md)

---

## File Structure

```
app/Modules/Auth/
├── SsoController.php                      ← NEW (cùng cấp AuthController.php)
├── Services/
│   ├── UserSyncService.php                ← NEW
│   └── Providers/
│       ├── SsoProvider.php                ← NEW (interface)
│       ├── SsoDanangProvider.php          ← NEW
│       └── CbccvcProvider.php             ← NEW
├── Requests/
│   ├── SsoExchangeRequest.php             ← NEW
│   └── CbccvcLoginRequest.php             ← NEW
└── Routes/
    └── auth.php                           ← MODIFIED

app/Modules/Core/
├── Models/
│   ├── User.php                           ← MODIFIED (thêm socials())
│   └── UserSocial.php                     ← NEW
└── Enums/
    └── SettingGroupEnum.php               ← MODIFIED

database/
├── migrations/
│   └── 2026_04_18_XXXXXX_create_user_socials_table.php  ← NEW
└── seeders/
    └── SettingSeeder.php                  ← MODIFIED

tests/
├── Unit/Auth/
│   └── UserSyncServiceTest.php            ← NEW
└── Feature/Auth/
    ├── SsoExchangeTest.php                ← NEW
    └── CbccvcLoginTest.php                ← NEW
```

---

## Task 1: Migration `user_socials` + UserSocial model

**Files:**
- Create: `database/migrations/2026_04_18_100000_create_user_socials_table.php`
- Create: `app/Modules/Core/Models/UserSocial.php`
- Modify: `app/Modules/Core/Models/User.php` (thêm `socials()`)

- [ ] **Step 1: Tạo migration file**

File: `database/migrations/2026_04_18_100000_create_user_socials_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_socials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_user_id', 191);
            $table->json('provider_data')->nullable();
            $table->timestamp('linked_at');
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_socials');
    }
};
```

- [ ] **Step 2: Tạo UserSocial model**

File: `app/Modules/Core/Models/UserSocial.php`

```php
<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSocial extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_data',
        'linked_at',
    ];

    protected $casts = [
        'provider_data' => 'array',
        'linked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Thêm relationship vào User model**

File: `app/Modules/Core/Models/User.php`

Thêm method sau `public function preference()` (khoảng dòng 71):

```php
public function socials()
{
    return $this->hasMany(UserSocial::class);
}
```

- [ ] **Step 4: Chạy migration + kiểm tra schema**

Run: `php artisan migrate`

Expected: migration `2026_04_18_100000_create_user_socials_table` chạy thành công. Verify: `php artisan tinker` → `Schema::hasTable('user_socials')` trả `true`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_04_18_100000_create_user_socials_table.php \
        app/Modules/Core/Models/UserSocial.php \
        app/Modules/Core/Models/User.php
git commit -m "feat(sso): add user_socials table and model"
```

---

## Task 2: Settings (SettingGroupEnum + SettingSeeder)

**Files:**
- Modify: `app/Modules/Core/Enums/SettingGroupEnum.php`
- Modify: `database/seeders/SettingSeeder.php`

- [ ] **Step 1: Thêm group mới vào enum**

File: `app/Modules/Core/Enums/SettingGroupEnum.php`

Thêm 3 case vào enum (sau `case Log`) và label tương ứng:

```php
    case SsoDanang = 'sso_danang';
    case SsoCbccvc = 'sso_cbccvc';
    case Auth = 'auth';
```

Thêm vào `label()` match:

```php
            self::SsoDanang => 'SSO Đà Nẵng',
            self::SsoCbccvc => 'CBCCVC',
            self::Auth => 'Xác thực chung',
```

- [ ] **Step 2: Thêm settings vào SettingSeeder**

File: `database/seeders/SettingSeeder.php`

Thêm vào cuối `$items` array (sau entry `log_retention_days`):

```php
        // SSO Đà Nẵng
        ['key' => 'sso_danang_enabled', 'value' => '0', 'group' => 'sso_danang', 'is_public' => true, 'type' => 'boolean', 'label' => 'Bật SSO Đà Nẵng', 'sort_order' => 1],
        ['key' => 'sso_danang_base_url', 'value' => 'https://sso.danang.gov.vn', 'group' => 'sso_danang', 'is_public' => true, 'type' => 'string', 'label' => 'Base URL', 'sort_order' => 2],
        ['key' => 'sso_danang_client_id', 'value' => null, 'group' => 'sso_danang', 'is_public' => true, 'type' => 'string', 'label' => 'Client ID', 'sort_order' => 3],
        ['key' => 'sso_danang_client_secret', 'value' => null, 'group' => 'sso_danang', 'is_public' => false, 'type' => 'string', 'label' => 'Client Secret', 'sort_order' => 4],
        ['key' => 'sso_danang_redirect_uri', 'value' => null, 'group' => 'sso_danang', 'is_public' => true, 'type' => 'string', 'label' => 'Redirect URI', 'sort_order' => 5],
        ['key' => 'sso_danang_scope', 'value' => 'openid profile email', 'group' => 'sso_danang', 'is_public' => true, 'type' => 'string', 'label' => 'Scope', 'sort_order' => 6],
        // CBCCVC
        ['key' => 'sso_cbccvc_enabled', 'value' => '0', 'group' => 'sso_cbccvc', 'is_public' => true, 'type' => 'boolean', 'label' => 'Bật CBCCVC', 'sort_order' => 1],
        ['key' => 'sso_cbccvc_base_url', 'value' => 'https://cbccvc.danang.gov.vn', 'group' => 'sso_cbccvc', 'is_public' => true, 'type' => 'string', 'label' => 'Base URL', 'sort_order' => 2],
        // Auth chung
        ['key' => 'auth_auto_create_default_role_id', 'value' => null, 'group' => 'auth', 'is_public' => false, 'type' => 'integer', 'label' => 'Role mặc định khi tạo user qua SSO', 'sort_order' => 1],
```

- [ ] **Step 3: Chạy seeder + verify**

Run: `php artisan db:seed --class=SettingSeeder`

Expected: output "Seeded" không lỗi. Verify: `php artisan tinker` → `\App\Modules\Core\Models\Setting::where('group', 'sso_danang')->count()` trả `6`.

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Core/Enums/SettingGroupEnum.php \
        database/seeders/SettingSeeder.php
git commit -m "feat(sso): add settings for SSO Danang, CBCCVC, and auth defaults"
```

---

## Task 3: `SsoProvider` interface

**Files:**
- Create: `app/Modules/Auth/Services/Providers/SsoProvider.php`

- [ ] **Step 1: Tạo interface**

File: `app/Modules/Auth/Services/Providers/SsoProvider.php`

```php
<?php

namespace App\Modules\Auth\Services\Providers;

interface SsoProvider
{
    /**
     * Nhận payload từ client, gọi ra provider, trả về userinfo chuẩn hóa.
     *
     * @param  array  $payload  Provider-specific: ['code' => ...] hoặc ['username' => ..., 'password' => ...]
     * @return array{email: string, name: string, sub: string, raw: array}
     *
     * @throws \RuntimeException Khi provider trả lỗi hoặc payload không hợp lệ.
     */
    public function getUserinfo(array $payload): array;

    /**
     * Provider key (dùng làm user_socials.provider value).
     */
    public function key(): string;
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Auth/Services/Providers/SsoProvider.php
git commit -m "feat(sso): add SsoProvider interface"
```

---

## Task 4: `UserSyncService` (test-first)

**Files:**
- Create: `tests/Unit/Auth/UserSyncServiceTest.php`
- Create: `app/Modules/Auth/Services/UserSyncService.php`

- [ ] **Step 1: Viết test cho case CREATE (user chưa tồn tại)**

File: `tests/Unit/Auth/UserSyncServiceTest.php`

```php
<?php

namespace Tests\Unit\Auth;

use App\Modules\Auth\Services\UserSyncService;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserSocial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserSyncService::class);
    }

    public function test_creates_new_user_when_email_and_social_both_missing(): void
    {
        $userinfo = [
            'email' => 'giangpt@danang.gov.vn',
            'name' => 'Phan Tấn Giang',
            'sub' => ' giangpt ',
            'raw' => ['upn' => 'giangpt'],
        ];

        $user = $this->service->syncFromUserinfo('sso_danang', $userinfo);

        $this->assertNotNull($user->id);
        $this->assertSame('giangpt@danang.gov.vn', $user->email);
        $this->assertSame('Phan Tấn Giang', $user->name);
        $this->assertNull($user->user_name);
        $this->assertSame('active', $user->status);
        $this->assertDatabaseHas('user_socials', [
            'user_id' => $user->id,
            'provider' => 'sso_danang',
            'provider_user_id' => 'giangpt',
        ]);
    }
}
```

- [ ] **Step 2: Chạy test để verify fail**

Run: `php artisan test --filter test_creates_new_user_when_email_and_social_both_missing`

Expected: FAIL — `Target class [App\Modules\Auth\Services\UserSyncService] does not exist` hoặc tương tự.

- [ ] **Step 3: Tạo UserSyncService (implementation tối thiểu để pass test)**

File: `app/Modules/Auth/Services/UserSyncService.php`

```php
<?php

namespace App\Modules\Auth\Services;

use App\Modules\Core\Models\Setting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserSocial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSyncService
{
    /**
     * Đồng bộ user từ userinfo của provider:
     * 1. Match user_socials theo (provider, provider_user_id) → load user, refresh provider_data.
     * 2. Không match → lookup users theo email.
     *    a. Có → link social vào user đó (không update user fields).
     *    b. Không có → tạo user mới + gán role default + link social.
     *
     * @param  array{email: string, name: string, sub: string, raw: array}  $userinfo
     */
    public function syncFromUserinfo(string $provider, array $userinfo): User
    {
        $providerUserId = trim((string) $userinfo['sub']);
        $email = trim((string) $userinfo['email']);
        $name = trim((string) $userinfo['name']);

        return DB::transaction(function () use ($provider, $providerUserId, $email, $name, $userinfo) {
            // 1. Match theo user_socials
            $social = UserSocial::where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($social) {
                $social->update(['provider_data' => $userinfo['raw'] ?? $userinfo]);

                return $social->user;
            }

            // 2. Lookup theo email (fallback)
            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'email' => $email,
                    'name' => $name,
                    'user_name' => null,
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'active',
                ]);

                if ($roleId = Setting::get('auth_auto_create_default_role_id')) {
                    $role = \Spatie\Permission\Models\Role::find($roleId);
                    if ($role) {
                        $user->assignRole($role);
                    }
                }
            }

            UserSocial::create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'provider_data' => $userinfo['raw'] ?? $userinfo,
                'linked_at' => now(),
            ]);

            return $user;
        });
    }
}
```

- [ ] **Step 4: Chạy test để verify pass**

Run: `php artisan test --filter test_creates_new_user_when_email_and_social_both_missing`

Expected: PASS.

- [ ] **Step 5: Thêm test case LINK (user email match)**

Thêm vào `tests/Unit/Auth/UserSyncServiceTest.php`:

```php
    public function test_links_social_to_existing_user_when_email_matches(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@danang.gov.vn',
            'name' => 'Existing Local User',
        ]);
        $originalPasswordHash = $existingUser->password;

        $userinfo = [
            'email' => 'existing@danang.gov.vn',
            'name' => 'SSO Returned Name',
            'sub' => 'xyz123',
            'raw' => [],
        ];

        $user = $this->service->syncFromUserinfo('sso_danang', $userinfo);

        $this->assertSame($existingUser->id, $user->id);
        $this->assertSame('Existing Local User', $user->fresh()->name, 'name không được ghi đè');
        $this->assertSame($originalPasswordHash, $user->fresh()->password, 'password không được ghi đè');
        $this->assertDatabaseHas('user_socials', [
            'user_id' => $existingUser->id,
            'provider' => 'sso_danang',
            'provider_user_id' => 'xyz123',
        ]);
    }
```

Run: `php artisan test --filter test_links_social_to_existing_user_when_email_matches`

Expected: PASS ngay (logic đã có sẵn từ step 3).

- [ ] **Step 6: Thêm test case RETURN (social đã link — lần đăng nhập sau)**

Thêm vào file test:

```php
    public function test_returns_linked_user_and_refreshes_provider_data_on_subsequent_login(): void
    {
        $user = User::factory()->create(['email' => 'user@danang.gov.vn']);
        UserSocial::create([
            'user_id' => $user->id,
            'provider' => 'sso_danang',
            'provider_user_id' => 'abc',
            'provider_data' => ['old' => 'data'],
            'linked_at' => now(),
        ]);

        $userinfo = [
            'email' => 'user@danang.gov.vn',
            'name' => 'User',
            'sub' => 'abc',
            'raw' => ['new' => 'data'],
        ];

        $resolved = $this->service->syncFromUserinfo('sso_danang', $userinfo);

        $this->assertSame($user->id, $resolved->id);
        $this->assertSame(1, UserSocial::where('user_id', $user->id)->count(), 'không tạo social trùng');
        $this->assertSame(['new' => 'data'], UserSocial::where('user_id', $user->id)->first()->provider_data);
    }
```

Run: `php artisan test --filter UserSyncServiceTest`

Expected: cả 3 test PASS.

- [ ] **Step 7: Thêm test case assign ROLE default khi tạo user mới**

Thêm vào file test:

```php
    public function test_assigns_default_role_to_newly_created_user_when_setting_configured(): void
    {
        $role = Role::create(['name' => 'Member', 'guard_name' => 'web']);
        Setting::create([
            'key' => 'auth_auto_create_default_role_id',
            'value' => (string) $role->id,
            'group' => 'auth',
            'is_public' => false,
            'type' => 'integer',
            'label' => 'Role mặc định',
            'sort_order' => 1,
        ]);

        $userinfo = [
            'email' => 'new@danang.gov.vn',
            'name' => 'New',
            'sub' => 'new1',
            'raw' => [],
        ];

        $user = $this->service->syncFromUserinfo('sso_danang', $userinfo);

        $this->assertTrue($user->hasRole('Member'));
    }
```

Run: `php artisan test --filter test_assigns_default_role_to_newly_created_user`

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add tests/Unit/Auth/UserSyncServiceTest.php \
        app/Modules/Auth/Services/UserSyncService.php
git commit -m "feat(sso): add UserSyncService with tests

Matching rule: (provider, provider_user_id) → email → create.
User fields are never overwritten after initial create/link.
Provider_data refreshes on every login for audit trail."
```

---

## Task 5: `SsoDanangProvider` (OAuth2 exchange)

**Files:**
- Create: `app/Modules/Auth/Services/Providers/SsoDanangProvider.php`
- Create: `tests/Unit/Auth/SsoDanangProviderTest.php`

- [ ] **Step 1: Viết test (fake Http calls)**

File: `tests/Unit/Auth/SsoDanangProviderTest.php`

```php
<?php

namespace Tests\Unit\Auth;

use App\Modules\Auth\Services\Providers\SsoDanangProvider;
use App\Modules\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoDanangProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSsoDanangSettings();
    }

    private function seedSsoDanangSettings(): void
    {
        foreach ([
            ['key' => 'sso_danang_base_url',       'value' => 'https://sso.example.test', 'type' => 'string'],
            ['key' => 'sso_danang_client_id',      'value' => 'test-client',              'type' => 'string'],
            ['key' => 'sso_danang_client_secret',  'value' => 'test-secret',              'type' => 'string'],
            ['key' => 'sso_danang_redirect_uri',   'value' => 'https://app.test/cb',      'type' => 'string'],
        ] as $row) {
            Setting::create($row + [
                'group' => 'sso_danang',
                'is_public' => false,
                'label' => $row['key'],
                'sort_order' => 0,
            ]);
        }
    }

    public function test_exchanges_code_for_userinfo(): void
    {
        Http::fake([
            'https://sso.example.test/oauth2/token' => Http::response([
                'access_token' => 'atok',
                'id_token' => 'itok',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'https://sso.example.test/oauth2/userinfo' => Http::response([
                'email' => 'giangpt@danang.gov.vn',
                'name' => 'Phan Tấn Giang',
                'sub' => ' giangpt ',
                'upn' => 'giangpt',
            ]),
        ]);

        $provider = app(SsoDanangProvider::class);
        $userinfo = $provider->getUserinfo(['code' => 'auth-code-xyz']);

        $this->assertSame('giangpt@danang.gov.vn', $userinfo['email']);
        $this->assertSame('Phan Tấn Giang', $userinfo['name']);
        $this->assertSame(' giangpt ', $userinfo['sub']);
        $this->assertIsArray($userinfo['raw']);
        $this->assertSame('giangpt', $userinfo['raw']['upn']);

        Http::assertSent(function ($req) {
            return $req->url() === 'https://sso.example.test/oauth2/token'
                && $req['grant_type'] === 'authorization_code'
                && $req['code'] === 'auth-code-xyz'
                && $req['client_id'] === 'test-client'
                && $req['client_secret'] === 'test-secret';
        });
    }

    public function test_throws_when_token_exchange_fails(): void
    {
        Http::fake([
            'https://sso.example.test/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSO Đà Nẵng token exchange failed');

        app(SsoDanangProvider::class)->getUserinfo(['code' => 'bad-code']);
    }
}
```

- [ ] **Step 2: Chạy test để verify fail**

Run: `php artisan test --filter SsoDanangProviderTest`

Expected: FAIL — class does not exist.

- [ ] **Step 3: Tạo SsoDanangProvider**

File: `app/Modules/Auth/Services/Providers/SsoDanangProvider.php`

```php
<?php

namespace App\Modules\Auth\Services\Providers;

use App\Modules\Core\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SsoDanangProvider implements SsoProvider
{
    public function key(): string
    {
        return 'sso_danang';
    }

    public function getUserinfo(array $payload): array
    {
        $code = $payload['code'] ?? null;
        if (! $code) {
            throw new RuntimeException('Missing authorization code.');
        }

        $baseUrl = rtrim((string) Setting::get('sso_danang_base_url'), '/');
        $clientId = Setting::get('sso_danang_client_id');
        $clientSecret = Setting::get('sso_danang_client_secret');
        $redirectUri = Setting::get('sso_danang_redirect_uri');

        if (! $baseUrl || ! $clientId || ! $clientSecret || ! $redirectUri) {
            throw new RuntimeException('Cấu hình SSO Đà Nẵng không đầy đủ.');
        }

        // 1. Exchange code → tokens
        $tokenResp = Http::asForm()->post($baseUrl.'/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (! $tokenResp->successful()) {
            throw new RuntimeException('SSO Đà Nẵng token exchange failed: '.$tokenResp->body());
        }

        $accessToken = $tokenResp->json('access_token');
        if (! $accessToken) {
            throw new RuntimeException('SSO Đà Nẵng did not return access_token.');
        }

        // 2. Fetch userinfo
        $userResp = Http::withToken($accessToken)->get($baseUrl.'/oauth2/userinfo');

        if (! $userResp->successful()) {
            throw new RuntimeException('SSO Đà Nẵng userinfo fetch failed: '.$userResp->body());
        }

        $raw = $userResp->json();

        if (empty($raw['email']) || empty($raw['sub'])) {
            throw new RuntimeException('SSO Đà Nẵng userinfo thiếu email hoặc sub.');
        }

        return [
            'email' => (string) $raw['email'],
            'name' => (string) ($raw['name'] ?? ''),
            'sub' => (string) $raw['sub'],
            'raw' => $raw,
        ];
    }
}
```

- [ ] **Step 4: Chạy test để verify pass**

Run: `php artisan test --filter SsoDanangProviderTest`

Expected: cả 2 test PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Auth/SsoDanangProviderTest.php \
        app/Modules/Auth/Services/Providers/SsoDanangProvider.php
git commit -m "feat(sso): add SsoDanangProvider for OAuth2 code exchange"
```

---

## Task 6: `CbccvcProvider` (direct API login)

**Files:**
- Create: `app/Modules/Auth/Services/Providers/CbccvcProvider.php`
- Create: `tests/Unit/Auth/CbccvcProviderTest.php`

> **Note:** Payload response của CBCCVC API chưa được cung cấp (ghi trong spec §9). Plan assume shape sau dựa trên doc `3.txt` ("RETURN data (array): data.user, data.jwt"). Nếu thực tế khác, chỉ cần sửa mapping trong `getUserinfo()` — không đụng phần khác của plan.

**Assumed payload:**
```json
{
  "data": {
    "user": {
      "id": 153208,
      "email": "giangpt@danang.gov.vn",
      "fullname": "Phan Tấn Giang",
      "username": "giangpt"
    },
    "jwt": "..."
  }
}
```

- [ ] **Step 1: Viết test**

File: `tests/Unit/Auth/CbccvcProviderTest.php`

```php
<?php

namespace Tests\Unit\Auth;

use App\Modules\Auth\Services\Providers\CbccvcProvider;
use App\Modules\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CbccvcProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create([
            'key' => 'sso_cbccvc_base_url',
            'value' => 'https://cbccvc.example.test',
            'group' => 'sso_cbccvc',
            'is_public' => true,
            'type' => 'string',
            'label' => 'Base URL',
            'sort_order' => 2,
        ]);
    }

    public function test_logs_in_and_returns_userinfo(): void
    {
        Http::fake([
            'https://cbccvc.example.test/*' => Http::response([
                'data' => [
                    'user' => [
                        'id' => 153208,
                        'email' => 'giangpt@danang.gov.vn',
                        'fullname' => 'Phan Tấn Giang',
                        'username' => 'giangpt',
                    ],
                    'jwt' => 'jwt-token-here',
                ],
            ]),
        ]);

        $userinfo = app(CbccvcProvider::class)->getUserinfo([
            'username' => 'giangpt',
            'password' => 'secret',
        ]);

        $this->assertSame('giangpt@danang.gov.vn', $userinfo['email']);
        $this->assertSame('Phan Tấn Giang', $userinfo['name']);
        $this->assertSame('153208', $userinfo['sub']);
    }

    public function test_throws_when_credentials_invalid(): void
    {
        Http::fake([
            'https://cbccvc.example.test/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CBCCVC login failed');

        app(CbccvcProvider::class)->getUserinfo(['username' => 'x', 'password' => 'y']);
    }
}
```

- [ ] **Step 2: Chạy test để verify fail**

Run: `php artisan test --filter CbccvcProviderTest`

Expected: FAIL — class does not exist.

- [ ] **Step 3: Tạo CbccvcProvider**

File: `app/Modules/Auth/Services/Providers/CbccvcProvider.php`

```php
<?php

namespace App\Modules\Auth\Services\Providers;

use App\Modules\Core\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CbccvcProvider implements SsoProvider
{
    public function key(): string
    {
        return 'cbccvc';
    }

    public function getUserinfo(array $payload): array
    {
        $username = $payload['username'] ?? null;
        $password = $payload['password'] ?? null;
        if (! $username || ! $password) {
            throw new RuntimeException('Missing CBCCVC credentials.');
        }

        $baseUrl = rtrim((string) Setting::get('sso_cbccvc_base_url'), '/');
        if (! $baseUrl) {
            throw new RuntimeException('Cấu hình CBCCVC không đầy đủ.');
        }

        $resp = Http::asForm()->post(
            $baseUrl.'/index.php?option=com_api&controller=core&task=login',
            ['username' => $username, 'password' => $password]
        );

        if ($resp->status() === 401) {
            throw new RuntimeException('CBCCVC login failed: invalid credentials.');
        }

        if (! $resp->successful()) {
            throw new RuntimeException('CBCCVC login failed: '.$resp->body());
        }

        $user = $resp->json('data.user');

        if (! is_array($user) || empty($user['id']) || empty($user['email'])) {
            throw new RuntimeException('CBCCVC response thiếu field user.');
        }

        return [
            'email' => (string) $user['email'],
            'name' => (string) ($user['fullname'] ?? $user['name'] ?? ''),
            'sub' => (string) $user['id'],
            'raw' => $resp->json('data'),
        ];
    }
}
```

- [ ] **Step 4: Chạy test để verify pass**

Run: `php artisan test --filter CbccvcProviderTest`

Expected: cả 2 test PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Auth/CbccvcProviderTest.php \
        app/Modules/Auth/Services/Providers/CbccvcProvider.php
git commit -m "feat(sso): add CbccvcProvider for direct API login

NOTE: user payload mapping based on documented shape;
adjust getUserinfo() field mapping if real payload differs."
```

---

## Task 7: Request validations

**Files:**
- Create: `app/Modules/Auth/Requests/SsoExchangeRequest.php`
- Create: `app/Modules/Auth/Requests/CbccvcLoginRequest.php`

- [ ] **Step 1: Tạo SsoExchangeRequest**

File: `app/Modules/Auth/Requests/SsoExchangeRequest.php`

```php
<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SsoExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|string|in:sso_danang',
            'code' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'provider.in' => 'Provider không hợp lệ.',
            'code.required' => 'Thiếu authorization code.',
        ];
    }
}
```

- [ ] **Step 2: Tạo CbccvcLoginRequest**

File: `app/Modules/Auth/Requests/CbccvcLoginRequest.php`

```php
<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CbccvcLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string',
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Tên đăng nhập không được để trống.',
            'password.required' => 'Mật khẩu không được để trống.',
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Auth/Requests/SsoExchangeRequest.php \
        app/Modules/Auth/Requests/CbccvcLoginRequest.php
git commit -m "feat(sso): add request validation for SSO exchange and CBCCVC login"
```

---

## Task 8: `SsoController` + Routes (feature tests)

**Files:**
- Create: `app/Modules/Auth/SsoController.php`
- Create: `tests/Feature/Auth/SsoExchangeTest.php`
- Create: `tests/Feature/Auth/CbccvcLoginTest.php`
- Modify: `app/Modules/Auth/Routes/auth.php`

- [ ] **Step 1: Viết feature test cho `/sso/exchange`**

File: `tests/Feature/Auth/SsoExchangeTest.php`

```php
<?php

namespace Tests\Feature\Auth;

use App\Modules\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed only settings needed
        foreach ([
            ['key' => 'sso_danang_enabled',       'value' => '1',                          'type' => 'boolean'],
            ['key' => 'sso_danang_base_url',      'value' => 'https://sso.example.test',   'type' => 'string'],
            ['key' => 'sso_danang_client_id',    'value' => 'test-client',                'type' => 'string'],
            ['key' => 'sso_danang_client_secret','value' => 'test-secret',                'type' => 'string'],
            ['key' => 'sso_danang_redirect_uri', 'value' => 'https://app.test/cb',        'type' => 'string'],
        ] as $row) {
            Setting::create($row + [
                'group' => 'sso_danang',
                'is_public' => true,
                'label' => $row['key'],
                'sort_order' => 0,
            ]);
        }
    }

    public function test_returns_404_when_provider_not_enabled(): void
    {
        Setting::where('key', 'sso_danang_enabled')->update(['value' => '0']);
        Setting::clearCache();

        $res = $this->postJson('/api/auth/sso/exchange', [
            'provider' => 'sso_danang',
            'code' => 'x',
        ]);

        $res->assertStatus(404);
    }

    public function test_returns_422_for_unknown_provider(): void
    {
        $res = $this->postJson('/api/auth/sso/exchange', [
            'provider' => 'unknown_provider',
            'code' => 'x',
        ]);

        $res->assertStatus(422);
    }

    public function test_exchanges_code_and_returns_token_with_user(): void
    {
        Http::fake([
            'https://sso.example.test/oauth2/token' => Http::response([
                'access_token' => 'atok',
                'expires_in' => 3600,
            ]),
            'https://sso.example.test/oauth2/userinfo' => Http::response([
                'email' => 'giangpt@danang.gov.vn',
                'name' => 'Phan Tấn Giang',
                'sub' => 'giangpt',
                'upn' => 'giangpt',
            ]),
        ]);

        $res = $this->postJson('/api/auth/sso/exchange', [
            'provider' => 'sso_danang',
            'code' => 'auth-code-xyz',
        ]);

        $res->assertOk();
        $res->assertJsonPath('success', true);
        $res->assertJsonStructure([
            'data' => ['access_token', 'token_type', 'user' => ['id', 'name', 'email']],
        ]);
        $this->assertDatabaseHas('users', ['email' => 'giangpt@danang.gov.vn']);
        $this->assertDatabaseHas('user_socials', [
            'provider' => 'sso_danang',
            'provider_user_id' => 'giangpt',
        ]);
    }

    public function test_returns_400_when_code_invalid(): void
    {
        Http::fake([
            'https://sso.example.test/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $res = $this->postJson('/api/auth/sso/exchange', [
            'provider' => 'sso_danang',
            'code' => 'bad',
        ]);

        $res->assertStatus(400);
    }

    public function test_returns_502_when_upstream_unreachable(): void
    {
        Http::fake([
            'https://sso.example.test/oauth2/token' => Http::response('Service Unavailable', 503),
        ]);

        $res = $this->postJson('/api/auth/sso/exchange', [
            'provider' => 'sso_danang',
            'code' => 'x',
        ]);

        $res->assertStatus(502);
    }
}
```

- [ ] **Step 2: Viết feature test cho `/sso/cbccvc/login`**

File: `tests/Feature/Auth/CbccvcLoginTest.php`

```php
<?php

namespace Tests\Feature\Auth;

use App\Modules\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CbccvcLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            ['key' => 'sso_cbccvc_enabled',  'value' => '1',                              'type' => 'boolean'],
            ['key' => 'sso_cbccvc_base_url', 'value' => 'https://cbccvc.example.test',    'type' => 'string'],
        ] as $row) {
            Setting::create($row + [
                'group' => 'sso_cbccvc',
                'is_public' => true,
                'label' => $row['key'],
                'sort_order' => 0,
            ]);
        }
    }

    public function test_returns_404_when_not_enabled(): void
    {
        Setting::where('key', 'sso_cbccvc_enabled')->update(['value' => '0']);
        Setting::clearCache();

        $res = $this->postJson('/api/auth/sso/cbccvc/login', [
            'username' => 'x', 'password' => 'y',
        ]);

        $res->assertStatus(404);
    }

    public function test_logs_in_and_returns_token_with_user(): void
    {
        Http::fake([
            'https://cbccvc.example.test/*' => Http::response([
                'data' => [
                    'user' => [
                        'id' => 153208,
                        'email' => 'giangpt@danang.gov.vn',
                        'fullname' => 'Phan Tấn Giang',
                        'username' => 'giangpt',
                    ],
                    'jwt' => 'ignored',
                ],
            ]),
        ]);

        $res = $this->postJson('/api/auth/sso/cbccvc/login', [
            'username' => 'giangpt',
            'password' => 'secret',
        ]);

        $res->assertOk();
        $res->assertJsonPath('data.user.email', 'giangpt@danang.gov.vn');
        $this->assertDatabaseHas('user_socials', [
            'provider' => 'cbccvc',
            'provider_user_id' => '153208',
        ]);
    }

    public function test_returns_401_when_credentials_invalid(): void
    {
        Http::fake([
            'https://cbccvc.example.test/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $res = $this->postJson('/api/auth/sso/cbccvc/login', [
            'username' => 'x', 'password' => 'wrong',
        ]);

        $res->assertStatus(401);
    }
}
```

- [ ] **Step 3: Chạy tests để verify fail**

Run: `php artisan test --filter "SsoExchangeTest|CbccvcLoginTest"`

Expected: tất cả FAIL (404 Route not found hoặc class not found).

- [ ] **Step 4: Tạo SsoController**

File: `app/Modules/Auth/SsoController.php`

```php
<?php

namespace App\Modules\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\CbccvcLoginRequest;
use App\Modules\Auth\Requests\SsoExchangeRequest;
use App\Modules\Auth\Services\Providers\CbccvcProvider;
use App\Modules\Auth\Services\Providers\SsoDanangProvider;
use App\Modules\Auth\Services\Providers\SsoProvider;
use App\Modules\Auth\Services\UserSyncService;
use App\Modules\Core\Enums\UserStatusEnum;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Resources\UserResource;
use RuntimeException;

/**
 * @group Auth
 *
 * Đăng nhập qua SSO (Đà Nẵng) hoặc CBCCVC.
 */
class SsoController extends Controller
{
    public function __construct(private UserSyncService $userSyncService) {}

    /**
     * OAuth code exchange (đa provider).
     *
     * @unauthenticated
     *
     * @bodyParam provider string required Provider key. Example: sso_danang
     * @bodyParam code string required Authorization code từ SSO Gateway. Example: abc123
     *
     * @response 200 {"success": true, "data": {"access_token": "1|xxx", "token_type": "Bearer", "user": {"id": 1, "name": "..."}}}
     */
    public function exchange(SsoExchangeRequest $request)
    {
        $provider = $request->validated('provider');

        if (! $this->isProviderEnabled($provider)) {
            return $this->error('Chức năng chưa được kích hoạt.', 404);
        }

        $impl = $this->resolveProvider($provider);

        return $this->runProvider(
            fn () => $impl->getUserinfo(['code' => $request->validated('code')]),
            $provider
        );
    }

    /**
     * CBCCVC direct login (username/password).
     *
     * @unauthenticated
     *
     * @bodyParam username string required Tên đăng nhập CBCCVC. Example: giangpt
     * @bodyParam password string required Mật khẩu.
     */
    public function cbccvcLogin(CbccvcLoginRequest $request)
    {
        if (! $this->isProviderEnabled('cbccvc')) {
            return $this->error('Chức năng chưa được kích hoạt.', 404);
        }

        return $this->runProvider(
            fn () => app(CbccvcProvider::class)->getUserinfo([
                'username' => $request->validated('username'),
                'password' => $request->validated('password'),
            ]),
            'cbccvc',
            invalidCredentialsStatus: 401
        );
    }

    /**
     * Map provider key (from user_socials.provider convention) → settings `enabled` key.
     * SSO Đà Nẵng: provider='sso_danang' → setting 'sso_danang_enabled'
     * CBCCVC:      provider='cbccvc'     → setting 'sso_cbccvc_enabled'
     */
    private function isProviderEnabled(string $provider): bool
    {
        $settingKey = match ($provider) {
            'sso_danang' => 'sso_danang_enabled',
            'cbccvc' => 'sso_cbccvc_enabled',
            default => null,
        };

        return $settingKey !== null && (bool) Setting::get($settingKey, false);
    }

    private function resolveProvider(string $key): SsoProvider
    {
        return match ($key) {
            'sso_danang' => app(SsoDanangProvider::class),
            default => throw new RuntimeException("Unknown provider: {$key}"),
        };
    }

    /**
     * Wrap provider call + user sync + token issuance; chuyển exception thành HTTP response.
     */
    private function runProvider(\Closure $fetchUserinfo, string $provider, int $invalidCredentialsStatus = 400)
    {
        try {
            $userinfo = $fetchUserinfo();
        } catch (RuntimeException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'invalid credentials') || str_contains($msg, 'invalid_grant')) {
                return $this->error($msg, $invalidCredentialsStatus);
            }

            return $this->error('Cổng đăng nhập không phản hồi: '.$msg, 502);
        }

        $user = $this->userSyncService->syncFromUserinfo($provider, $userinfo);

        if ($user->status !== UserStatusEnum::Active->value) {
            return $this->forbidden('Tài khoản của bạn đã bị khóa');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve(),
        ], 'Đăng nhập thành công.');
    }
}
```

- [ ] **Step 5: Thêm routes**

File: `app/Modules/Auth/Routes/auth.php`

Thay thế toàn bộ nội dung:

```php
<?php

use App\Modules\Auth\AuthController;
use App\Modules\Auth\SsoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/switch-organization', [AuthController::class, 'switchOrganization'])->middleware('auth:sanctum');

Route::prefix('sso')->group(function () {
    Route::post('exchange', [SsoController::class, 'exchange']);
    Route::post('cbccvc/login', [SsoController::class, 'cbccvcLogin']);
});
```

- [ ] **Step 6: Chạy tests để verify pass**

Run: `php artisan test --filter "SsoExchangeTest|CbccvcLoginTest"`

Expected: tất cả 7 test PASS.

- [ ] **Step 7: Chạy full test suite để đảm bảo không regression**

Run: `php artisan test`

Expected: tất cả test PASS (không có test nào fail do thay đổi này).

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Auth/SsoController.php \
        app/Modules/Auth/Routes/auth.php \
        tests/Feature/Auth/SsoExchangeTest.php \
        tests/Feature/Auth/CbccvcLoginTest.php
git commit -m "feat(sso): add SsoController with exchange and cbccvc/login endpoints

Endpoints respect provider enabled flag, dispatch to provider services,
call UserSyncService, and issue Sanctum token. Response shape matches
existing /api/auth/login."
```

---

## Task 9: Update existing `SettingController` to hide sensitive keys (if needed)

**Files:**
- Check: `app/Modules/Core/Controllers/SettingController.php` (path may differ)

- [ ] **Step 1: Locate settings endpoint & check current filter logic**

Run: `grep -r "is_public" app/Modules/ --include="*.php" -l`

Kiểm tra xem controller/service nào đang serve public settings. Thường là route `GET /api/settings/public`.

- [ ] **Step 2: Verify `client_secret` bị filter**

Viết test nhanh:

Run: `php artisan test --filter Setting` (nếu có test settings sẵn)

HOẶC manual check: gọi endpoint public settings trong tinker/Postman sau khi seed → confirm `sso_danang_client_secret` KHÔNG xuất hiện.

- [ ] **Step 3: Nếu cần filter thêm**

Nếu current filter chỉ dựa vào `is_public`, thì đã đủ — seeder đã set `is_public=false` cho `client_secret` và `auth_auto_create_default_role_id`. **Không cần thay đổi code.**

Nếu không, thêm whitelist/blacklist trong controller tương ứng (code cụ thể phụ thuộc structure hiện có).

- [ ] **Step 4: Commit (nếu có thay đổi)**

```bash
git add <changed-files>
git commit -m "chore(sso): ensure client_secret excluded from public settings endpoint"
```

---

## Task 10: Manual smoke test + documentation

- [ ] **Step 1: Seed + smoke test tinker**

Run: `php artisan db:seed --class=SettingSeeder` (upsert các setting mới).

Tinker:
```bash
php artisan tinker
```

```php
\App\Modules\Core\Models\Setting::where('group', 'like', 'sso_%')->count();  // → 8
\App\Modules\Core\Models\Setting::where('group', 'auth')->count();           // → 1
```

- [ ] **Step 2: Kiểm tra Scribe docs regenerate (nếu project dùng)**

Run: `php artisan scribe:generate` (nếu package `knuckleswtf/scribe` được dùng — composer.json có).

Expected: 2 endpoint mới xuất hiện trong nhóm "Auth".

- [ ] **Step 3: Commit docs (nếu có thay đổi auto-generated)**

```bash
git add public/docs storage/app/scribe 2>/dev/null
git commit -m "docs(sso): regenerate API docs for SSO endpoints" || echo "no doc changes"
```

- [ ] **Step 4: Final verification**

Run: `php artisan test`

Expected: tất cả test PASS.

Run: `vendor/bin/pint --test` (hoặc `./vendor/bin/pint --test` trên unix).

Expected: PASS (code style OK).

---

## Notes & Out-of-scope (per spec §2, §9, §10)

- **Frontend changes:** SPA cần:
  - Đọc `sso_danang_enabled` + `sso_cbccvc_enabled` từ `GET /api/settings/public` để render nút.
  - Trang `/login`: thêm nút "Đăng nhập SSO Đà Nẵng" và form "Đăng nhập CBCCVC".
  - Trang mới `/auth/sso/danang/callback`: parse `code` + `state`, verify state từ sessionStorage, POST `/api/auth/sso/exchange`, lưu token, redirect `/dashboard`.
  - **Plan này chỉ cover backend.** FE làm ở PR riêng.

- **CBCCVC response payload:** Giả định shape `{data: {user: {id, email, fullname, username}, jwt}}` theo doc. Nếu khác, chỉ sửa mapping trong `CbccvcProvider::getUserinfo()` — các task khác không bị ảnh hưởng.

- **SSO-side logout:** không implement v1. Local logout via `/api/auth/logout` đủ.

- **Frontend state verification:** FE-side sessionStorage only, không có backend state cache.

- **Email match takeover risk:** chấp nhận được cho SSO Đà Nẵng + CBCCVC (provider tin cậy). Khi thêm Google/FB sau này cần xem xét lại (check `email_verified` của Google, cân nhắc disable email match cho FB).
