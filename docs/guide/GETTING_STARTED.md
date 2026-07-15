# Getting Started — QLCV Backend

> Ngày tạo: 00:00:00 28/06/2026  
> Cập nhật lần cuối: 00:00:00 28/06/2026

Chào mừng. Doc này cho bạn (dev mới hoặc dev đã quen Laravel nhưng chưa quen repo) đủ context để **đọc code không bị lạc** và **chạy task đầu tiên trong vài giờ thay vì vài ngày**. Không phải tài liệu auto-generate — viết như thể tôi đang ngồi cạnh giải thích.

Đọc theo thứ tự là tốt nhất, nhưng các section đứng độc lập được. Khi cần đào sâu chỗ nào, có pointer tới doc chi tiết ở cuối.

---

## 1. Hệ thống này làm gì

QLCV (quản lý công việc) là backend API cho hệ thống nội bộ đa tổ chức (multi-tenant), phục vụ 3 nhóm chức năng chính:

- **Auth** — đăng nhập, đổi mật khẩu, **chuyển tổ chức làm việc** (1 user có thể thuộc nhiều tổ chức).
- **Core** — user, role, permission, organization, settings, log truy cập, notification.
- **TaskAssignment** — luồng nghiệp vụ chính: văn bản giao việc → chia thành các đầu việc cho phòng ban/cá nhân → assignee submit báo cáo → quản lý confirm. Có nhắc nhở deadline (reminder), thống kê, xuất Excel.
- **Meeting** — quản lý cuộc họp nội bộ: lập lịch họp, mời thành viên, agenda + biểu quyết, ghi nhận RSVP, báo cáo sau họp, xuất kết quả Excel.

Tất cả qua REST API, FE riêng (Vue/React, không trong repo này). Auth bằng Bearer token (Sanctum).

---

## 2. Tech stack ngắn gọn

| Layer | Tech |
|-------|------|
| Framework | Laravel 12, PHP 8.2+ |
| DB | MySQL (qua Sail/Docker) |
| Auth | `laravel/sanctum` (Bearer token), `spatie/laravel-permission` v7 với **teams feature** |
| Files / media | `spatie/laravel-medialibrary` |
| Excel | `maatwebsite/excel` (export + import) |
| Push | `kreait/firebase-php` (FCM) |
| API docs | `knuckleswtf/scribe` (sinh từ docblock controller) |
| Tree | `kalnoy/nestedset` (cho cấu trúc cây — vd organization) |
| Geo | `stevebauman/location` (lookup country từ IP cho log) |
| Test | PHPUnit 11 |
| Dev runner | Laravel Sail (Docker compose) |

Timezone mặc định: **Asia/Ho_Chi_Minh** (`config/app.php`). Đừng giả định UTC — sẽ trật giờ.

---

## 3. Chạy lần đầu

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan storage:link
./vendor/bin/sail npm install # nếu cần asset
```

Chạy dev:
```bash
./vendor/bin/sail composer dev # API + queue + log + vite cùng lúc
```

Chạy riêng:
```bash
./vendor/bin/sail artisan serve
./vendor/bin/sail artisan queue:listen --tries=1 --timeout=0
./vendor/bin/sail artisan schedule:work # nếu test cron (notification reminders)
```

Test:
```bash
./vendor/bin/sail artisan test
./vendor/bin/sail artisan test --filter=YourTestName
```

API docs (Scribe): `/docs` sau khi chạy `sail artisan scribe:generate`.

---

## 4. Cấu trúc thư mục — tour nhanh

**`app/`** — code chính
- `Console/Commands/` — Artisan commands (cleanup seeds, simulate notifications)
- `Http/Controllers/` — chỉ controller chung như `DeployController`. Module controllers nằm trong `app/Modules`
- `Modules/Auth/` — đăng nhập, sso, password
- `Modules/Core/` — user/role/permission/org/setting/log/notification
- `Modules/TaskAssignment/` — nghiệp vụ chính
- `Modules/Meeting/` — quản lý cuộc họp (có thêm `Concerns/`, `Events/`, `Middleware/`, `Policies/` so với pattern cơ bản)
- `Providers/` — `AppServiceProvider`, `NotificationServiceProvider`
- `Services/Notification/` — service xuyên module, là notification engine

**`bootstrap/app.php`** — ★ entry config: middleware aliases, routing, exception handler

**`config/`** — Laravel config thuần

**`database/`**
- `factories/` — cho test
- `migrations/` — mọi schema change
- `seeders/` — `PermissionSeeder` bắt buộc chạy trong test có check role
- `maxmind/` — GeoLite2 db cho lookup country IP

**`docs/`** — xem [docs/README.md](../README.md) để có bản đồ đầy đủ

**`routes/`**
- `api.php` — ★ xem để hiểu pipeline middleware
- `console.php` — đăng ký scheduled commands (cron)
- `web.php`

**`tests/`**
- `Feature/` — đa số test ở đây (subfolders: `Auth/`, `Notification/`, `TaskAssignment/`, `Core/`, `Meeting/`)
- `Unit/`

Quy ước quan trọng:
- **Mỗi module ở `app/Modules/<Name>/` đều có structure giống nhau** (xem section 5). Tìm 1 file → đoán được file tương đương ở module khác.
- **Routes nằm CẠNH module**, không phải gom hết vào `routes/api.php`. File `routes/api.php` chỉ `require` các file route con từ `app/Modules/*/Routes/`.
- `app/Services/Notification/` là exception — nó không phải module nghiệp vụ mà là **engine xuyên module**: bất cứ module nào cần gửi notification đều xài chung.

---

## 5. Pattern 1 module — soi qua TaskAssignment

Trong `app/Modules/TaskAssignment/`:

| Folder | Vai trò |
|--------|---------|
| `Controllers/` | Mỏng. Nhận request → gọi service → trả về resource |
| `Services/` | Business logic. Phần lớn code thực sự ở đây |
| `Models/` | Eloquent models. Có `HasOrganizationScope` nếu multi-tenant |
| `Requests/` | FormRequest validators (`StoreXxxRequest`, `UpdateXxxRequest`…) |
| `Resources/` | API resources (transform model → JSON shape) |
| `Routes/` | File route riêng cho module (require từ `routes/api.php`) |
| `Observers/` | Eloquent observers (vd reschedule reminder khi `end_at` đổi) |
| `Enums/` | PHP 8.2 backed enums (status, role, priority…) |
| `Exports/` | Excel exports (`Maatwebsite\Excel`) |
| `Imports/` | Excel imports |

Khi thêm 1 resource (vd `TaskAssignmentXyz`), đi qua 6 file gần như cố định:
1. `database/migrations/*_create_task_assignment_xyz_table.php`
2. `app/Modules/TaskAssignment/Models/TaskAssignmentXyz.php`
3. `app/Modules/TaskAssignment/Services/TaskAssignmentXyzService.php`
4. `app/Modules/TaskAssignment/Controllers/TaskAssignmentXyzController.php`
5. `app/Modules/TaskAssignment/Requests/{Store,Update}XyzRequest.php`
6. `app/Modules/TaskAssignment/Resources/XyzResource.php` + `Routes/task_assignment_xyz.php`
7. Đăng ký route group trong `routes/api.php`

**Bộ method chuẩn** mỗi resource phải implement:

| Method service | Endpoint tương ứng |
|----------------|--------------------|
| `stats` | `GET /stats` |
| `index` | `GET /` |
| `show` | `GET /{id}` |
| `store` | `POST /` |
| `update` | `PUT /{id}` |
| `destroy` | `DELETE /{id}` |
| `bulkDestroy` | `DELETE /bulk-delete` |
| `bulkUpdateStatus` | `PATCH /bulk-status` |
| `changeStatus` | `PATCH /{id}/status` |
| `export` | `GET /export` |
| `import` | `POST /import` |

Module `Meeting` có thêm: `Concerns/`, `Events/`, `Middleware/`, `Policies/`. Khi thêm module mới phức tạp, tham khảo Meeting làm reference.

---

## 6. Multi-tenant — concept quan trọng nhất phải nắm

Hệ thống đa tổ chức. Mỗi user có thể thuộc nhiều `organization`. Khi gọi API, FE **bắt buộc gửi header**:

```
X-Organization-Id: 1
```

Nếu thiếu → middleware `SetPermissionsTeamId` trả 422. Nếu user không có quyền vào org đó → 403.

Có 3 mảnh ráp lại:

### 6.1. Spatie Permission "teams" mode
`config/permission.php` bật `teams = true`. Mỗi role/permission gắn với 1 organization. Cùng 1 user có thể là `Super Admin` ở org A nhưng `Member` ở org B. Spatie cần biết "đang xét trong org nào" qua `setPermissionsTeamId($orgId)`.

### 6.2. Middleware `set.permissions.team`
Sau `auth:sanctum`, middleware này:
1. Đọc `X-Organization-Id` từ header
2. Kiểm tra org tồn tại + active
3. Kiểm tra user có thuộc org đó
4. Gọi `setPermissionsTeamId($orgId)` → các check `$user->can(...)` mới đúng

### 6.3. Trait `HasOrganizationScope`
Model nghiệp vụ multi-tenant `use HasOrganizationScope`. Trait này:
- **Khi create**: tự gán `organization_id = getPermissionsTeamId()` nếu chưa có
- **Khi query**: thêm `WHERE organization_id = getPermissionsTeamId()` qua global scope

→ Code trong service **KHÔNG cần** `where('organization_id', ...)` — trait lo. Nhưng cẩn thận: nếu test quên `setPermissionsTeamId()` → query trả rỗng.

**Trong test** nhớ:
```php
setPermissionsTeamId($this->org->id); // đầu mỗi test scope multi-tenant
```

Chi tiết đầy đủ: [system/AUTH_TENANT.md](../system/AUTH_TENANT.md).

---

## 7. Middleware pipeline — đọc 1 lần, hiểu mãi

| Alias | Tác dụng |
|-------|----------|
| `permission` | Check user có permission `xxx.yyy` không |
| `set.permissions.team` | Đọc `X-Organization-Id`, validate, set Spatie team context |
| `log.activity` | Tự động ghi 1 row vào `log_activities` cho MỌI request |
| `ensure.route.org` | Đảm bảo route model binding thuộc đúng org — nếu không → 404 |
| `sync.fcm.token` | Đọc `X-FCM-Token` header → cập nhật `users.fcm_token` |
| `notification.module` | Set `module_key` cho notification config endpoints |

Pipeline điển hình:
```php
Route::middleware(['auth:sanctum', 'set.permissions.team', 'sync.fcm.token', 'log.activity'])->group(function () {
    Route::prefix('task-assignment-items')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_item.php');
    });
});
```

**Public routes** (`/api/auth/*`, `/api/.../public`, `/deploy/webhook`) — chỉ gắn `log.activity`, không qua auth.

---

## 8. Auth & permission

Sanctum bearer token. Login qua `POST /api/auth/login` → trả token. FE gắn `Authorization: Bearer <token>` cho mọi request sau đó.

Permission được seed bởi `PermissionSeeder`. Convention tên: `<resource-kebab>.<action-camel>`, vd `task-assignment-items.index`, `log-activities.stats`.

Roles thường gặp: `Super Admin`, `Quản trị`, `Admin`, custom roles per org.

**Trong test** muốn bypass mọi permission:
```php
$this->seed(\Database\Seeders\PermissionSeeder::class);
setPermissionsTeamId($org->id); // SAU seed
$user = User::factory()->create();
$user->assignRole('Super Admin');
Sanctum::actingAs($user);
```

> ⚠ Nếu quên `setPermissionsTeamId` SAU seed — role sẽ assign vào default org của seeder → 403. Đây là gotcha thường gặp nhất.

### Khi thêm resource / action mới

Cập nhật `database/seeders/PermissionSeeder.php` rồi re-seed:
```bash
sail artisan db:seed --class=PermissionSeeder
```

---

## 9. Notification engine — flow tổng quát

`app/Services/Notification/` là engine **xuyên module**.

Flow 1 notification:
1. **Module** fire event hoặc gọi `NotificationDispatcher::dispatch(...)`
2. **Dispatcher** tạo row `notifications` + N row `notification_deliveries` (1/channel)
3. **Dispatcher** push `SendDeliveryJob` vào queue `notifications`
4. **Queue worker** pick job → gọi channel sender → gửi thật
5. **Job** update `delivery.status` = `sent` / `failed` / `skipped`

3 loại event: **Instant** (fire ngay), **Reminder** (lập lịch `remind_at` → cron quét), **In-app only** vs **multi-channel**.

Cron `notifications:process-reminders` đăng ký ở `routes/console.php`, chạy `everyMinute()->withoutOverlapping()`.

Doc sâu: [guides/notification-flow-behavior.md](../guides/notification-flow-behavior.md), [guides/notification-new-module-integration.md](../guides/notification-new-module-integration.md).

---

## 10. Conventions

### Response shape
Trait `RespondsWithJson` chuẩn hóa:
```json
{ "success": true,  "message": "...", "data": {...} }
{ "success": false, "message": "...", "errors": {...}, "code": "VALIDATION_ERROR | FORBIDDEN | ..." }
```

### Naming
- **DB columns** + **API field names**: `snake_case`
- **PHP classes / methods**: `PascalCase` / `camelCase`
- **Permission strings**: `kebab.camel` — vd `task-assignment-items.bulkDestroy`
- **URL paths**: `kebab-case`, plural cho resource

### Datetime
- App timezone: `Asia/Ho_Chi_Minh`. KHÔNG dùng UTC implicit.
- Cột datetime lưu `Y-m-d H:i:s` (giây cũng có ý nghĩa).
- Nếu thấy giờ lệch 7h → có ai đó set TZ ở chỗ không nên.

### Filter pattern
List endpoint nhận: `search`, `status`, `from_date`, `to_date`, `sort_by`, `sort_order`, `limit`. Validate qua `FilterRequest`. Service nhận `$request->all()` → `Model::filter($filters)`.

### HTTP method conventions

| Thao tác | Method | Route mẫu |
|----------|--------|-----------|
| Xóa 1 | `DELETE` | `/{id}` |
| Xóa hàng loạt | `DELETE` | `/bulk-delete` — body `{"ids":[...]}` |
| Đổi trạng thái 1 | `PATCH` | `/{id}/status` |
| Đổi trạng thái hàng loạt | `PATCH` | `/bulk-status` |
| Sắp xếp lại | `PATCH` | `/reorder` |

`bulk-delete` dùng `DELETE` với body JSON — Laravel parse được. Dùng `POST` là di sản scaffold cũ, không áp dụng cho code mới.

### DB::transaction() — khi nào dùng
- **Dùng**: luồng ghi nhiều bước có phụ thuộc nhau.
- **Không dùng**: chỉ đọc, hoặc 1 câu ghi đơn lẻ.
- **Lưu ý file**: nếu trong transaction có upload/xóa file → `try/catch` cleanup file khi exception.

### MediaService
Mọi upload/xóa media đi qua `App\Modules\Core\Services\MediaService`. Không gọi trực tiếp `addMedia()` hay `Storage::put/delete`.

---

## 11. Testing — patterns thường dùng

```php
class XyzTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
    }

    public function test_xxx(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/...');

        $res->assertOk();
    }
}
```

Khi test **time-sensitive**: `Carbon::setTestNow('2026-04-28 14:37:00');`

Khi test queue: `Queue::fake();` → `Queue::assertPushed(SendDeliveryJob::class, 1);`

Khi test **cross-organization isolation**: phải `setPermissionsTeamId($orgB->id)` trước khi query data của orgB.

---

## 12. Workflow doc — superpowers + changelogs

### `docs/superpowers/specs/` + `docs/superpowers/plans/`
Cho feature lớn: brainstorm → spec → implementation plan → execute.
- `YYYY-MM-DD-<topic>-design.md` — spec đã chốt
- `YYYY-MM-DD-<topic>.md` — plan chia task

### `docs/changelogs/`
Khi BE thêm/đổi API, viết 1 file `YYYY-MM-DD-<topic>-fe.md`:
- Breaking hay không
- API changes (diff before/after)
- TS interfaces gợi ý
- Migration code sample

→ FE đọc changelog là implement được, không phải hỏi BE từng field.

---

## 13. Khi nào đào sâu chỗ nào

| Câu hỏi | Đọc |
|---------|-----|
| Schema CSDL trông ra sao | [database/ERD.md](../database/ERD.md) |
| Cấu trúc dự án thiết kế thế nào | [STRUCTURE_DESIGN.md](../STRUCTURE_DESIGN.md) |
| Module TaskAssignment chi tiết | [answer/tong-hop-module-task-assignment.md](../answer/tong-hop-module-task-assignment.md) |
| Notification flow + cách thêm module mới | [guides/notification-flow-behavior.md](../guides/notification-flow-behavior.md) |
| API list | `sail artisan scribe:generate` → `public/docs/index.html` hoặc `docs/api/` |
| Format export/import Excel | [answer/model-export-import-theo-module.md](../answer/model-export-import-theo-module.md) |
| Migrate FE khi BE đổi API | `changelogs/` — tìm file `YYYY-MM-DD-topic-fe` |
| Lỗi không hiểu | [guide/TROUBLESHOOTING.md](TROUBLESHOOTING.md) |

---

Doc này được duy trì manually — nếu phát hiện gì lệch thực tế, sửa thẳng vào file này và cập nhật `Cập nhật lần cuối`.
