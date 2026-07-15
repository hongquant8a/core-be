# Auth & Multi-tenant — QLCV Backend

> Ngày tạo: 00:00:00 28/06/2026  
> Cập nhật lần cuối: 00:00:00 28/06/2026

Giải thích cơ chế xác thực, phân quyền và multi-tenant. Đây là concept quan trọng nhất cần nắm trước khi đọc/viết code nghiệp vụ.

---

## Tổng quan luồng

```
HTTP Request
  ↓
auth:sanctum                  ← Xác thực Bearer token → inject $user vào request
  ↓
set.permissions.team          ← Đọc X-Organization-Id → validate → setPermissionsTeamId()
  ↓
permission:{resource}.{action} ← Spatie check $user->can() trong context org hiện tại
  ↓
ensure.route.org              ← (nếu có route model binding) đảm bảo model thuộc đúng org
  ↓
Controller → Service → Model (HasOrganizationScope tự scope query theo org)
```

---

## 1. Authentication — Laravel Sanctum

- Token type: **Bearer token** (stateless API)
- Login: `POST /api/auth/login` → trả `token`
- FE gắn: `Authorization: Bearer {token}` cho mọi request sau đó
- Token không có expiry mặc định — logout là xóa token

---

## 2. Multi-tenant — 3 thành phần ráp lại

### 2.1. Header X-Organization-Id

Mỗi request nghiệp vụ **bắt buộc** có header:
```
X-Organization-Id: 1
```
Thiếu → 422. User không có quyền vào org đó → 403.

### 2.2. Middleware `set.permissions.team`

Class: `app/Modules/Core/Middleware/SetPermissionsTeamId.php`

Làm 4 việc:
1. Đọc `X-Organization-Id` từ header
2. Validate org tồn tại + active
3. Validate user có thuộc org đó
4. Gọi `setPermissionsTeamId($orgId)` — từ đây Spatie mới check đúng permissions

### 2.3. Trait `HasOrganizationScope`

Mọi Model nghiệp vụ thuộc tenant thêm `use HasOrganizationScope`.

**Khi create:** tự gán `organization_id = getPermissionsTeamId()` nếu chưa set.  
**Khi query:** thêm `WHERE organization_id = getPermissionsTeamId()` qua Eloquent global scope.

→ Service **KHÔNG cần** `->where('organization_id', ...)` — trait lo tự động.

**Bypass khi cần (seed, import, cross-tenant job):**
```php
Model::withoutGlobalScope('organization')->create([...]);
Model::withoutGlobalScopes()->where(...)->get();
```

---

## 3. Permission — Spatie Laravel Permission (teams mode)

### Cấu hình
- `config/permission.php`: `teams = true`
- Guard duy nhất: `web` (cho cả web + API Sanctum)
- 1 user có thể có role khác nhau ở mỗi org

### Format permission string
```
{resource-kebab}.{actionCamel}
```
Ví dụ: `meeting-rooms.index`, `task-assignment-items.bulkDestroy`, `log-activities.stats`

**Resource** trùng prefix route (kebab-case). **Action** dùng camelCase.

### Khai báo permission
File: `database/seeders/PermissionSeeder.php`

```php
'meeting-rooms' => ['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import', 'stats'],
```

Sau khi thêm mới: `sail artisan db:seed --class=PermissionSeeder`

### Check permission trong route
```php
Route::middleware(['permission:meeting-rooms.store,web'])->group(function () {
    Route::post('/', [MeetingRoomController::class, 'store']);
});
```

### Roles thường gặp
`Super Admin`, `Quản trị`, `Admin`, custom roles per org.

---

## 4. Middleware chi tiết

| Alias | Class | Tác dụng |
|---|---|---|
| `permission` | Spatie PermissionMiddleware | Check permission `{key},web` |
| `set.permissions.team` | SetPermissionsTeamId | Set org context (bắt buộc trước permission check) |
| `log.activity` | LogActivity | Ghi log mỗi request vào `log_activities` |
| `ensure.route.org` | EnsureRouteModelsBelongToOrganization | Route binding → 404 nếu sai org |
| `sync.fcm.token` | SyncFcmToken | Đọc `X-FCM-Token` → update `users.fcm_token` |
| `notification.module` | SetNotificationModule | Set `module_key` cho notification config |

---

## 5. Pattern trong test

```php
protected function setUp(): void
{
    parent::setUp();
    $this->seed(\Database\Seeders\PermissionSeeder::class); // 1. seed trước
    $this->org = Organization::firstOrCreate(['slug' => 'test'], [...]);
    setPermissionsTeamId($this->org->id);                   // 2. set team SAU seed
}

public function test_something(): void
{
    $user = User::factory()->create();
    $user->assignRole('Super Admin');                        // 3. assign role sau khi đã set team
    Sanctum::actingAs($user);

    $this->withHeader('X-Organization-Id', (string) $this->org->id)
         ->getJson('/api/...')
         ->assertOk();
}
```

**Thứ tự quan trọng:** seed → setPermissionsTeamId → assignRole. Đảo là role assign vào sai org → 403.

---

## 6. Cross-tenant safety rules

| Rule | Lý do |
|---|---|
| `store`/`import` gán `organization_id` từ `getPermissionsTeamId()`, không nhận từ client | Tránh user tạo data vào org khác |
| `show`/`update`/`destroy` dùng `ensure.route.org` middleware | Tránh user thao tác record của org khác |
| Bulk action filter `organization_id` trước khi xử lý | Tránh bulk delete/update chéo org |
| Background Job nhận `organization_id` qua constructor | `auth()` không có trong queue worker |
| Schedule Command loop qua từng org, dùng `withoutGlobalScope` | `HasOrganizationScope` không hoạt động nếu không có request context |
