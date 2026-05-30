# Onboarding — qlcv backend

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

API docs (Scribe): `/docs` sau khi chạy `php artisan scribe:generate`.

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

**`docs/`**
- `ONBOARDING.md` — ← bạn đang đọc
- `DATABASE_DESIGN.md` — mô tả CSDL
- `STRUCTURE_DESIGN.md` — cấu trúc dự án ở dạng decision log
- `api/` — docs API generate
- `changelogs/` — changelog cho FE migrate (xem section 12), format `.md` hoặc `.txt`
- `guides/` — hướng dẫn flow notification
- `answer/` — phân tích/trả lời các câu hỏi nghiệp vụ
- `superpowers/` — specs + plans cho feature lớn (workflow brainstorm → plan → impl)

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
2. `app/Modules/TaskAssignment/Models/TaskAssignmentXyz.php` (model + relations + `HasOrganizationScope` nếu cần)
3. `app/Modules/TaskAssignment/Services/TaskAssignmentXyzService.php` (CRUD logic, query)
4. `app/Modules/TaskAssignment/Controllers/TaskAssignmentXyzController.php`
5. `app/Modules/TaskAssignment/Requests/{Store,Update}XyzRequest.php`
6. `app/Modules/TaskAssignment/Resources/XyzResource.php` + `Routes/task_assignment_xyz.php`
7. Đăng ký route group trong `routes/api.php`

**Bộ method chuẩn** mỗi resource phải implement (trừ khi không phù hợp nghiệp vụ):

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

Module `Core` cùng pattern, chỉ khác là controller nằm trực tiếp trong `app/Modules/Core/` (lịch sử, không phải lỗi).

Module `Meeting` cùng pattern nhưng có thêm: `Concerns/` (trait dùng chung nội bộ module), `Events/` (domain events), `Middleware/` (middleware riêng), `Policies/` (authorization policy). Khi thêm module mới phức tạp, tham khảo Meeting làm reference.

---

## 6. Multi-tenant — concept quan trọng nhất phải nắm

Hệ thống đa tổ chức. Mỗi user có thể thuộc nhiều `organization`. Khi gọi API, FE **bắt buộc gửi header**:

```
X-Organization-Id: 1
```

Nếu thiếu → middleware [SetPermissionsTeamId](../app/Modules/Core/Middleware/SetPermissionsTeamId.php) trả 422 với message Vietnamese. Nếu user không có quyền vào org đó → 403.

Có 3 mảnh ráp lại:

### 6.1. Spatie Permission "teams" mode
Cấu hình `config/permission.php` bật `teams = true`. Mỗi role/permission gắn với 1 organization. Cùng 1 user có thể là `Super Admin` ở org A nhưng `Member` ở org B. Spatie cần biết "đang xét trong org nào" qua hàm global `setPermissionsTeamId($orgId)`.

### 6.2. Middleware `set.permissions.team`
Sau `auth:sanctum`, middleware này:
1. Đọc `X-Organization-Id` từ header
2. Kiểm tra org tồn tại + active
3. Kiểm tra user có row trong `task_assignment_users` (hoặc tương đương) với org đó
4. Gọi `setPermissionsTeamId($orgId)` → từ đây các check `$user->can(...)` mới đúng

### 6.3. Trait `HasOrganizationScope`
Model nghiệp vụ multi-tenant `use HasOrganizationScope`. Trait này:
- **Khi create**: tự gán `organization_id = getPermissionsTeamId()` nếu chưa có
- **Khi query**: thêm `WHERE organization_id = getPermissionsTeamId()` qua global scope

→ Code trong service KHÔNG cần `where('organization_id', ...)` — trait lo. Nhưng cẩn thận: nếu test quên `setPermissionsTeamId()` → query trả rỗng, debug rất rối.

**Trong test** nhớ:
```php
setPermissionsTeamId($this->org->id); // đầu mỗi test scope multi-tenant
```

Đa số test base class hoặc trait helper đã làm sẵn.

---

## 7. Middleware pipeline — đọc 1 lần, hiểu mãi

[bootstrap/app.php](../bootstrap/app.php) đăng ký 6 alias. Mỗi cái làm 1 việc:

| Alias | Class | Tác dụng |
|-------|-------|----------|
| `permission` | Spatie `PermissionMiddleware` | Check user có permission `xxx.yyy` không. Dùng đầy route. |
| `set.permissions.team` | [SetPermissionsTeamId](../app/Modules/Core/Middleware/SetPermissionsTeamId.php) | Đọc `X-Organization-Id`, validate, set Spatie team context (xem section 6) |
| `log.activity` | [LogActivity](../app/Modules/Core/Middleware/LogActivity.php) | **Tự động ghi 1 row vào `log_activities` cho MỌI request** (trừ `/up`, `/api/notifications/me/unread-count`) — biết để không hoảng khi thấy table phình to |
| `ensure.route.org` | [EnsureRouteModelsBelongToOrganization](../app/Modules/Core/Middleware/EnsureRouteModelsBelongToOrganization.php) | Nếu route có model binding (vd `{taskAssignmentItem}`) — đảm bảo model đó thuộc đúng org đang work. Nếu không → 404 (giả vờ không tồn tại) |
| `sync.fcm.token` | [SyncFcmToken](../app/Modules/Core/Middleware/SyncFcmToken.php) | Đọc `X-FCM-Token` header → cập nhật `users.fcm_token` (cho push notification). Silent — không fail nếu thiếu |
| `notification.module` | [SetNotificationModule](../app/Modules/Core/Middleware/SetNotificationModule.php) | Set `module_key` vào request attributes, dùng cho notification config endpoints |

Pipeline điển hình của 1 route nghiệp vụ ([routes/api.php](../routes/api.php)):

```php
Route::middleware(['auth:sanctum', 'set.permissions.team', 'sync.fcm.token', 'log.activity'])->group(function () {
    Route::prefix('task-assignment-items')->group(function () {
        require base_path('app/Modules/TaskAssignment/Routes/task_assignment_item.php');
    });
});
```

Bên trong file route con thường có thêm `permission:xxx,web` per-route.

**Public routes** (`/api/auth/*`, `/api/.../public`, `/deploy/webhook`) — chỉ gắn `log.activity`, không qua auth.

---

## 8. Auth & permission

Sanctum bearer token. Login qua `POST /api/auth/login` → trả token. FE gắn `Authorization: Bearer <token>` cho mọi request sau đó.

Permission được seed bởi `PermissionSeeder` (tự sinh từ enum/registry — đọc file để biết list). Convention tên: `<resource-kebab>.<action-camel>`, vd `task-assignment-items.index`, `log-activities.stats`, `task-assignment-departments.syncUsers`.

Roles thường gặp: `Super Admin`, `Quản trị`, `Admin`, custom roles per org.

**Trong test** muốn bypass mọi permission:
```php
$this->seed(\Database\Seeders\PermissionSeeder::class);
setPermissionsTeamId($org->id); // sau seed, set team về org test
$user = User::factory()->create();
$user->assignRole('Super Admin');
Sanctum::actingAs($user);
```

(Nếu quên `setPermissionsTeamId` SAU seed — role sẽ assign vào default org của seeder, không phải org test → 403 khi gọi endpoint. Đây là gotcha thường gặp nhất.)

### Khi thêm resource / action mới

Bắt buộc cập nhật `database/seeders/PermissionSeeder.php` — mảng `PERMISSIONS` khai báo theo dạng:

```php
'resource-name' => ['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import', 'stats'],
```

Sau đó re-seed để đồng bộ:
```bash
sail artisan db:seed --class=PermissionSeeder
# hoặc nếu dev local muốn fresh:
sail artisan migrate:fresh --seed
```

Format permission string: `{resource-kebab}.{actionCamel}` — vd `meeting-rooms.bulkDestroy`, `task-assignment-items.changeStatus`. Guard dùng `web` cho cả API Sanctum.

---

## 9. Notification engine — flow tổng quát

`app/Services/Notification/` là engine **xuyên module**. Module nghiệp vụ chỉ cần fire event hoặc gọi dispatcher, engine lo phần còn lại.

Flow 1 notification từ lúc trigger đến lúc tới user:

1. **Module** trigger — fire event (vd `DocumentIssued`) hoặc gọi trực tiếp `NotificationDispatcher::dispatch(...)`
2. **Dispatcher** tạo 1 row `notifications` + N row `notification_deliveries` (1 row/channel: mail, sms, zalo, fcm)
3. **Dispatcher** push `SendDeliveryJob` cho từng delivery vào queue `notifications`
4. **Queue worker** (`queue:work --queue=notifications`) pick job ra
5. **Job** gọi `NotificationService` → channel sender tương ứng → gửi thật
6. **Job** update `delivery.status` = `sent` / `failed` / `skipped`

3 loại event:
- **Instant**: vd `document_issued` — fire ngay khi state đổi.
- **Reminder**: `reminder_before` / `reminder_on` / `reminder_after` — không fire ngay; **lập lịch** (lưu vào `task_assignment_reminders` với `remind_at`) → cron `notifications:process-reminders` chạy mỗi phút quét row đến giờ → fire.
- **In-app only** vs **multi-channel** (mail/sms/zalo/fcm) — config qua `NotificationEventConfig` + `NotificationSchedule`.

Cron `notifications:process-reminders` đăng ký ở [routes/console.php](../routes/console.php), chạy `everyMinute()->withoutOverlapping()`. Trong dev: `php artisan schedule:work` để giả lập cron.

Có 2 command simulator để test thủ công:
- `notification:simulate` — full flow create task → submit report → confirm
- `notification:simulate-reminder` — chỉ nhắc, schedule ngắn (phút) để xem cron fire theo wall-clock

Doc sâu: [docs/guides/notification-flow-behavior.md](guides/notification-flow-behavior.md), [docs/guides/notification-new-module-integration.md](guides/notification-new-module-integration.md).

---

## 10. Conventions

### Response shape
Trait [RespondsWithJson](../app/Modules/Core/Traits/RespondsWithJson.php) chuẩn hóa:

**Success:**
```json
{ "success": true, "message": "...", "data": {...} }
```

**Error:**
```json
{ "success": false, "message": "...", "errors": {...}, "code": "VALIDATION_ERROR | FORBIDDEN | ..." }
```

Validation errors → 422 với `errors` object. Permission denied → 403. Cấu hình ở [bootstrap/app.php](../bootstrap/app.php) (block `withExceptions`).

### Naming
- **DB columns** + **API field names**: `snake_case`.
- **PHP classes / methods**: `PascalCase` / `camelCase`.
- **Permission strings**: `kebab.camel` (xem section 8).
- **URL paths**: `kebab-case`, plural cho resource (`/task-assignment-items`).

### Datetime
- App timezone: `Asia/Ho_Chi_Minh`. KHÔNG dùng UTC implicit.
- Cột datetime lưu `Y-m-d H:i:s` (giây cũng có ý nghĩa — vd `remind_at` so sánh chính xác đến giây).
- Carbon mặc định trong app dùng app timezone. Nếu thấy giờ lệch 7h → có ai đó set TZ ở chỗ không nên.

### Filter pattern
Hầu hết list endpoint nhận query params: `search`, `status`, `from_date`, `to_date`, `sort_by`, `sort_order`, `limit`. Validate qua [FilterRequest](../app/Modules/Core/Requests/FilterRequest.php). Service nhận `$request->all()` rồi gọi `Model::filter($filters)` (scope `scopeFilter` ở model).

### HTTP method conventions
Quy tắc bắt buộc — **không dùng POST thay cho DELETE/PATCH**:

| Thao tác | Method | Route mẫu |
|----------|--------|-----------|
| Xóa 1 | `DELETE` | `/{id}` |
| Xóa hàng loạt | `DELETE` | `/bulk-delete` |
| Đổi trạng thái 1 | `PATCH` | `/{id}/status` |
| Đổi trạng thái hàng loạt | `PATCH` | `/bulk-status` |
| Sắp xếp lại | `PATCH` | `/reorder` |

`bulk-delete` dùng `DELETE` với body JSON `{ "ids": [...] }` — Laravel parse được JSON body cho DELETE qua `$request->input('ids')`. Dùng `POST` cho bulk-delete là di sản scaffold cũ, không áp dụng cho code mới.

### FormRequest conventions
Mỗi FormRequest phải có đủ 3 method:

```php
public function rules(): array { /* ... */ }

public function messages(): array
{
    return [
        'name.required' => 'Tên là bắt buộc.',
        'status.in'     => 'Trạng thái không hợp lệ.',
        // ... bao phủ mọi rule đang dùng, tiếng Việt
    ];
}

public function attributes(): array
{
    return [
        'name'   => 'tên',
        'status' => 'trạng thái',
        // ... map tên thân thiện cho từng field validate
    ];
}
```

Với request dùng cho endpoint API, bổ sung thêm:
- `bodyParameters()` — mô tả field cho Scribe sinh API doc.
- `queryParameters()` — với FilterRequest hoặc request dùng query params.

### DB::transaction() — khi nào dùng
- **Dùng**: luồng ghi nhiều bước có phụ thuộc nhau (create + sync relation + xóa row liên quan, import nhiều bản ghi...).
- **Không dùng**: chỉ đọc, hoặc chỉ 1 câu ghi đơn lẻ — tránh lạm dụng.
- **Lưu ý file**: nếu trong transaction có upload/xóa file, phải `try/catch` và cleanup file khi exception để tránh lệch DB vs storage.

### MediaService — upload/xóa file
Mọi thao tác upload/xóa media phải đi qua `App\Modules\Core\Services\MediaService`. Không gọi trực tiếp `addMedia()` hay `Storage::put/delete` trong service của module khác.

```php
// Đúng
$this->mediaService->upload($model, $request->file('attachment'));

// Sai — gọi thẳng Spatie trong service nghiệp vụ
$model->addMedia($file)->toMediaCollection('attachments');
```

### Public catalog API pattern
Danh mục dùng cho dropdown/form công khai (không cần auth) phải có 2 endpoint riêng, đặt **ngoài** nhóm `auth:sanctum` trong `routes/api.php`:

| Endpoint | Dùng khi |
|----------|----------|
| `GET /api/{resource}/public` | Cần data đầy đủ theo Resource hiện có |
| `GET /api/{resource}/public-options` | Dropdown — chỉ cần `id`, `name`, `description`; filter `status=active` |

`public-options` phải select tối thiểu cột cần thiết và sort ổn định (tránh phân trang nặng cho dropdown).

### Scribe PHPDoc conventions
Scribe sinh API doc từ PHPDoc trong controller. Mỗi controller/action phải có:

```php
/**
 * @group Meeting - Phòng họp
 *
 * Quản lý phòng họp nội bộ.
 */
class MeetingRoomController extends Controller
{
    /**
     * Danh sách phòng họp
     *
     * @header X-Organization-Id required ID tổ chức làm việc. Example: 1
     * @queryParam search string Tìm theo tên. Example: phòng A
     * @queryParam status string Lọc theo trạng thái (active/inactive). Example: active
     */
    public function index(FilterRequest $request) { ... }

    /**
     * Danh sách công khai (dropdown)
     *
     * @unauthenticated  ← bắt buộc với public endpoint, không thì Scribe hiển thị "requires auth" sai
     */
    public function publicOptions() { ... }

    /**
     * Xuất Excel
     *
     * Xuất ra các trường: id, name, capacity, status, created_by, updated_by, created_at, updated_at.
     *
     * @header X-Organization-Id required ID tổ chức làm việc. Example: 1
     */
    public function export() { ... }
}
```

Tham khảo style đầy đủ: `app/Modules/Core/` controllers hoặc `app/Modules/Meeting/` controllers gần nhất. Sau khi thêm/sửa PHPDoc: `sail artisan scribe:generate` và kiểm tra `public/docs/`.

---

## 11. Testing — patterns thường dùng

```php
class XyzTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class); // chỉ khi cần check role
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

Luôn dùng `RefreshDatabase`. Sanctum + role + header — pattern lặp lại y hệt.

Khi test **time-sensitive** (cron, reminder):
```php
Carbon::setTestNow('2026-04-28 14:37:00');
```

Khi test queue:
```php
Queue::fake();
// ... dispatch logic
Queue::assertPushed(SendDeliveryJob::class, 1);
```

Khi test **cross-organization isolation**: phải `setPermissionsTeamId($orgB->id)` trước khi seed/query data của orgB, rồi `setPermissionsTeamId($orgA->id)` lại trước khi query orgA. Nếu không, `HasOrganizationScope` global scope sẽ bị nhầm lẫn.

Test base class ở `tests/TestCase.php` — hiện tại minimalmá, không có helper. Có trait `tests/Concerns/InteractsWithNotifications.php` cho test notification (seed config + helper enable event).

---

## 12. Workflow doc — superpowers + changelogs

Có 2 thư mục doc theo workflow:

### `docs/superpowers/specs/` + `docs/superpowers/plans/`
Cho feature **lớn**: brainstorm → spec → implementation plan → execute. Mỗi feature có:
- `YYYY-MM-DD-<topic>-design.md` — spec đã chốt với user
- `YYYY-MM-DD-<topic>.md` — plan chia task

Đọc mấy file gần đây (vd `2026-04-28-test-cronjob-design.md`) để thấy format. Đây không phải bắt buộc cho mọi PR — chỉ cho feature đa-step.

### `docs/changelogs/`
**Quan trọng**: khi BE thêm/đổi API, viết 1 file changelog `YYYY-MM-DD-<topic>-fe.md` cho FE migrate. Format có sẵn ở các file gần đây:
- Nêu **breaking** hay không
- API changes (diff before/after)
- TS interfaces gợi ý
- Migration code sample
- List tests đã có

→ FE đọc changelog là implement được, không phải hỏi BE từng field.

---

## 13. Gotchas thường gặp (top 5)

1. **Quên `X-Organization-Id` header** → 422 "Vui lòng gửi header X-Organization-Id...". Không phải bug, là invariant của hệ thống.

2. **Permission test fail vì team context sai**: pattern là `seed(PermissionSeeder)` → `setPermissionsTeamId($org->id)` → `assignRole(...)`. Nếu đảo thứ tự, role assign vào default org của seeder.

3. **Số trên dashboard lệch nhau vài đơn vị**: middleware `log.activity` tự log mỗi request. Giữa 2 lần gọi stats API, count tăng. Không phải bug. Xem [docs/changelogs/2026-04-28-log-activity-dashboard-fe.md](changelogs/2026-04-28-log-activity-dashboard-fe.md) để hiểu hơn.

4. **`HasOrganizationScope` "ăn mất" data trong test**: nếu setup `setPermissionsTeamId($orgA->id)` rồi query model thuộc orgB → trả empty. Switch team trước khi query.

5. **`remind_at` lệch 7h**: timezone bị set về UTC ở đâu đó (CI env, test setUp). App phải chạy `Asia/Ho_Chi_Minh`. Có 4 test riêng cho timezone trong `tests/Feature/Notification/ProcessRemindersTimezoneTest.php` — chạy xanh ⇒ TZ ổn.

6. **`bulk-delete` route dùng sai HTTP method**: nếu thấy route `POST /bulk-delete` trong code mới → sai convention. Phải là `DELETE /bulk-delete`. Client gửi body JSON `{ "ids": [...] }` qua DELETE — Laravel parse bình thường. POST là di sản scaffold cũ.

---

## 14. Khi nào đào sâu chỗ nào

| Câu hỏi | Đọc |
|---------|-----|
| Schema CSDL trông ra sao | [docs/DATABASE_DESIGN.md](DATABASE_DESIGN.md) |
| Cấu trúc dự án thiết kế thế nào | [docs/STRUCTURE_DESIGN.md](STRUCTURE_DESIGN.md) |
| Module TaskAssignment chi tiết | [docs/answer/tong-hop-module-task-assignment.md](answer/tong-hop-module-task-assignment.md), [docs/answer/phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md](answer/phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md) |
| Module Meeting chi tiết | `docs/superpowers/specs/` — tìm file spec meeting gần nhất; `app/Modules/Meeting/` — xem code trực tiếp |
| Notification flow + cách thêm module mới | [docs/guides/notification-flow-behavior.md](guides/notification-flow-behavior.md), [docs/guides/notification-new-module-integration.md](guides/notification-new-module-integration.md) |
| API list | `sail artisan scribe:generate` → mở `public/docs/index.html`. Hoặc đọc trực tiếp `docs/api/`. |
| Format export/import Excel | [docs/answer/model-export-import-theo-module.md](answer/model-export-import-theo-module.md) |
| Cách thêm label cho LogActivity | `app/Modules/Core/Middleware/LogActivity.php` — `resourceLabel()`, `actionLabels`, `pathActions` |
| Workflow viết feature mới | Mở 1 spec gần đây trong `docs/superpowers/specs/` để xem format |
| Migrate FE khi BE đổi API | `docs/changelogs/` — luôn có changelog cho mỗi PR đổi API |

---

## 15. Khi đụng phải vấn đề lạ

Trước khi đào source, check:
1. **Migration đã chạy chưa?** `sail artisan migrate:status`
2. **Permission seeded?** `sail artisan db:seed --class=PermissionSeeder`
3. **Queue worker đang chạy?** Reminder/notification cần worker xử lý job. `composer dev` chạy sẵn.
4. **Schedule worker đang chạy?** Cron reminder cần `schedule:work`.
5. **Header `X-Organization-Id` đúng chưa?** Hơn 80% lỗi 403/422 trên dev là do thiếu/sai header này.

Nếu test xanh local nhưng đỏ CI → hay là TZ. Đảm bảo CI set `APP_TIMEZONE=Asia/Ho_Chi_Minh`.

---

Chúc làm việc vui. Doc này được duy trì manually — nếu phát hiện gì lệch thực tế, sửa thẳng vào file này, đừng đợi.
