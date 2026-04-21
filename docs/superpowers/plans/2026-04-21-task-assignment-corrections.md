# TaskAssignment Corrections Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development or executing-plans. Steps use `- [ ]` checkboxes.

**Goal:** Áp dụng 4 correction cho module TaskAssignment đã quyết: user nhiều phòng ban, confirm/lock báo cáo, sync enum reminder, cập nhật spec.

**Architecture:** Database migrations + service layer tweaks + 1 endpoint mới + enum sync + spec update note. Không thay đổi kiến trúc lớn — chủ yếu bồi đắp missing features + alignment với spec.

**Spec reference:** [docs/superpowers/specs/2026-04-21-task-assignment-corrections.md](../specs/2026-04-21-task-assignment-corrections.md)

**Commit policy:** Không commit từng task. Subagent chỉ implement + test. Controller gom 1 commit cuối.

---

## File Structure

```
database/migrations/
├── 2026_04_21_100000_alter_task_assignment_users_multi_dept.php       ← NEW
└── 2026_04_21_100001_add_confirm_lock_to_task_assignment_item_reports.php ← NEW

app/Modules/TaskAssignment/
├── Enums/
│   └── TaskReminderStatusEnum.php                                     ← MODIFIED
├── Models/
│   ├── TaskAssignmentUser.php                                         ← MODIFIED (fillable)
│   └── TaskAssignmentItemReport.php                                   ← MODIFIED (fillable, casts)
├── Controllers/
│   └── TaskAssignmentItemReportController.php                         ← MODIFIED (add confirm action)
├── Services/
│   ├── TaskAssignmentDepartmentService.php                            ← MODIFIED (multi-dept logic)
│   └── TaskAssignmentReportService.php                                ← MODIFIED (confirm method, reject-when-locked)
├── Requests/
│   └── ConfirmReportRequest.php                                       ← NEW
└── Routes/
    └── task_assignment_item_report.php                                ← MODIFIED (add confirm route)

database/seeders/
└── PermissionSeeder.php                                               ← MODIFIED (add confirm perm)

docs/
├── phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md               ← MODIFIED (correction notes)

tests/
└── Feature/TaskAssignment/
    ├── ReportConfirmTest.php                                          ← NEW
    └── UserMultiDepartmentTest.php                                    ← NEW
```

---

## Task 1: Migration — `task_assignment_users` multi-department

**Files:**
- Create: `database/migrations/2026_04_21_100000_alter_task_assignment_users_multi_dept.php`

- [ ] **Step 1: Tạo migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('task_assignment_department_id');
        });

        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->dropUnique('task_assignment_users_user_id_organization_id_unique');
        });

        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'task_assignment_department_id', 'organization_id'],
                'ta_users_user_dept_org_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->dropUnique('ta_users_user_dept_org_unique');
        });

        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->unique(['user_id', 'organization_id']);
        });

        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
```

- [ ] **Step 2: Chạy migrate**

```bash
php artisan migrate
```

Expected: new migration run, no error.

Verify schema:
```bash
php artisan tinker --execute="var_dump(Schema::hasColumn('task_assignment_users', 'is_primary'));"
```
Expect `bool(true)`.

---

## Task 2: Model — `TaskAssignmentUser` fillable

**File:** `app/Modules/TaskAssignment/Models/TaskAssignmentUser.php`

- [ ] **Step 1: Read current model**

- [ ] **Step 2: Thêm `is_primary` vào `$fillable`**

Thêm `'is_primary'` vào mảng `$fillable`.

Thêm cast nếu `casts()` method có — `'is_primary' => 'boolean'`.

---

## Task 3: Service — multi-department logic

**File:** `app/Modules/TaskAssignment/Services/TaskAssignmentDepartmentService.php`

- [ ] **Step 1: Refactor `syncUsers()` — không delete toàn bộ user cũ nữa**

Current code (dòng 114-133) **xóa hết user của department** rồi insert lại → **destructive** với multi-dept model (sẽ mất records của user trong dept khác).

Thay bằng logic delta:

```php
public function syncUsers(TaskAssignmentDepartment $department, array $userIds): void
{
    $orgId = getPermissionsTeamId();

    // Lấy current users trong THIS department (scope orgId)
    $currentUserIds = TaskAssignmentUser::where('task_assignment_department_id', $department->id)
        ->where('organization_id', $orgId)
        ->pluck('user_id')
        ->all();

    $toRemove = array_diff($currentUserIds, $userIds);
    $toAdd = array_diff($userIds, $currentUserIds);

    if ($toRemove) {
        TaskAssignmentUser::where('task_assignment_department_id', $department->id)
            ->where('organization_id', $orgId)
            ->whereIn('user_id', $toRemove)
            ->delete();
    }

    foreach ($toAdd as $userId) {
        $hasPrimary = TaskAssignmentUser::where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->where('is_primary', true)
            ->exists();

        TaskAssignmentUser::create([
            'user_id' => $userId,
            'task_assignment_department_id' => $department->id,
            'organization_id' => $orgId,
            'status' => 'active',
            'is_primary' => ! $hasPrimary,  // auto primary nếu chưa có
        ]);
    }
}
```

- [ ] **Step 2: Thêm `setPrimary()` method**

```php
public function setPrimary(int $userId, int $departmentId): void
{
    $orgId = getPermissionsTeamId();

    // Đảm bảo record (user, dept, org) tồn tại
    $target = TaskAssignmentUser::where('user_id', $userId)
        ->where('task_assignment_department_id', $departmentId)
        ->where('organization_id', $orgId)
        ->firstOrFail();

    \DB::transaction(function () use ($userId, $target, $orgId) {
        // Reset primary cho mọi record cùng (user, org)
        TaskAssignmentUser::where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->update(['is_primary' => false]);

        // Set primary cho record target
        $target->update(['is_primary' => true]);
    });
}
```

- [ ] **Step 3: Refactor `removeUser()` — giữ nguyên, nhưng nếu record bị xóa là primary, auto promote record khác**

```php
public function removeUser(TaskAssignmentDepartment $department, int $userId): void
{
    $orgId = getPermissionsTeamId();

    \DB::transaction(function () use ($department, $userId, $orgId) {
        $removed = TaskAssignmentUser::where('task_assignment_department_id', $department->id)
            ->where('user_id', $userId)
            ->first();

        if (! $removed) {
            return;
        }

        $wasPrimary = $removed->is_primary;
        $removed->delete();

        if ($wasPrimary) {
            // Promote 1 record còn lại thành primary (nếu có)
            TaskAssignmentUser::where('user_id', $userId)
                ->where('organization_id', $orgId)
                ->limit(1)
                ->update(['is_primary' => true]);
        }
    });
}
```

---

## Task 4: Migration — `task_assignment_item_reports` confirm/lock

**File:** `database/migrations/2026_04_21_100001_add_confirm_lock_to_task_assignment_item_reports.php`

- [ ] **Step 1: Tạo migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_assignment_item_reports', function (Blueprint $table) {
            $table->boolean('manager_confirmed')->default(false)->after('report_document_content');
            $table->unsignedBigInteger('manager_confirmed_by')->nullable()->after('manager_confirmed');
            $table->dateTime('manager_confirmed_at')->nullable()->after('manager_confirmed_by');
            $table->text('manager_confirm_note')->nullable()->after('manager_confirmed_at');
            $table->boolean('is_locked')->default(false)->after('manager_confirm_note');
            $table->dateTime('locked_at')->nullable()->after('is_locked');
            $table->unsignedBigInteger('locked_by')->nullable()->after('locked_at');

            $table->foreign('manager_confirmed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['manager_confirmed', 'is_locked'], 'ta_item_reports_confirmed_locked_index');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_item_reports', function (Blueprint $table) {
            $table->dropIndex('ta_item_reports_confirmed_locked_index');
            $table->dropForeign(['manager_confirmed_by']);
            $table->dropForeign(['locked_by']);
            $table->dropColumn([
                'manager_confirmed',
                'manager_confirmed_by',
                'manager_confirmed_at',
                'manager_confirm_note',
                'is_locked',
                'locked_at',
                'locked_by',
            ]);
        });
    }
};
```

- [ ] **Step 2: Chạy migrate + verify**

```bash
php artisan migrate
php artisan tinker --execute="var_dump(Schema::hasColumn('task_assignment_item_reports', 'is_locked'));"
```

---

## Task 5: Model — `TaskAssignmentItemReport` fillable + casts

**File:** `app/Modules/TaskAssignment/Models/TaskAssignmentItemReport.php`

- [ ] **Step 1: Read + update**

Thêm vào `$fillable`:
```php
'manager_confirmed',
'manager_confirmed_by',
'manager_confirmed_at',
'manager_confirm_note',
'is_locked',
'locked_at',
'locked_by',
```

Thêm casts (nếu dùng `casts()` method — follow pattern hiện có trong model):
```php
'manager_confirmed' => 'boolean',
'manager_confirmed_at' => 'datetime',
'is_locked' => 'boolean',
'locked_at' => 'datetime',
```

---

## Task 6: Request — `ConfirmReportRequest`

**File:** `app/Modules/TaskAssignment/Requests/ConfirmReportRequest.php`

- [ ] **Step 1: Tạo file**

```php
<?php

namespace App\Modules\TaskAssignment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_note' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_note.max' => 'Ghi chú xác nhận không được vượt quá 2000 ký tự.',
        ];
    }
}
```

---

## Task 7: Service — `confirm()` method + reject update-when-locked

**File:** `app/Modules/TaskAssignment/Services/TaskAssignmentReportService.php`

- [ ] **Step 1: Thêm `confirm(TaskAssignmentItemReport $report, ?string $note): TaskAssignmentItemReport`**

Logic:
- Nếu `$report->is_locked === true` → throw `RuntimeException('Báo cáo đã khóa, không thể xác nhận lại.')`.
- Update trong transaction:
  - `manager_confirmed = true`
  - `manager_confirmed_by = auth()->id()`
  - `manager_confirmed_at = now()`
  - `manager_confirm_note = $note`
  - `is_locked = true`
  - `locked_at = now()`
  - `locked_by = auth()->id()`
- Return fresh model.

- [ ] **Step 2: Modify `update()` method — reject if locked**

Thêm đầu method `update()`:

```php
if ($report->is_locked) {
    throw new \RuntimeException('Báo cáo đã khóa, không thể sửa.');
}
```

- [ ] **Step 3: Modify `destroy()` — tương tự**

```php
if ($report->is_locked) {
    throw new \RuntimeException('Báo cáo đã khóa, không thể xóa.');
}
```

---

## Task 8: Controller — `confirm` action

**File:** `app/Modules/TaskAssignment/Controllers/TaskAssignmentItemReportController.php`

- [ ] **Step 1: Inject handling + read current imports**

Thêm import: `use App\Modules\TaskAssignment\Requests\ConfirmReportRequest;`

- [ ] **Step 2: Thêm action `confirm`**

```php
/**
 * Xác nhận báo cáo và khóa.
 *
 * @urlParam id integer required ID báo cáo. Example: 1
 * @bodyParam confirm_note string optional Ghi chú xác nhận. Example: Đạt quy định
 *
 * @response 200 {"success": true, "data": {...}, "message": "Đã xác nhận báo cáo."}
 * @response 422 {"success": false, "message": "Báo cáo đã khóa, không thể xác nhận lại."}
 */
public function confirm(ConfirmReportRequest $request, int $id)
{
    $report = TaskAssignmentItemReport::findOrFail($id);

    try {
        $updated = $this->service->confirm($report, $request->validated('confirm_note'));
    } catch (\RuntimeException $e) {
        return $this->error($e->getMessage(), 422);
    }

    return $this->success(
        (new ReportResource($updated))->resolve(),
        'Đã xác nhận báo cáo.'
    );
}
```

Cũng thêm try/catch tương tự ở `update()` và `destroy()` hiện có để chuyển RuntimeException → 422 response.

---

## Task 9: Route — `PATCH /{id}/confirm`

**File:** `app/Modules/TaskAssignment/Routes/task_assignment_item_report.php`

- [ ] **Step 1: Đọc file hiện tại, thêm route**

```php
Route::patch('/{id}/confirm', [TaskAssignmentItemReportController::class, 'confirm'])
    ->middleware('can:task-assignment-item-reports.confirm');
```

Đặt trong cùng group với các route reports khác.

---

## Task 10: Permission — `task-assignment-item-reports.confirm`

**File:** `database/seeders/PermissionSeeder.php`

- [ ] **Step 1: Edit $PERMISSIONS**

Tìm entry `'task-assignment-item-reports'` và thêm `'confirm'`:

```php
'task-assignment-item-reports' => [
    'index', 'show', 'store', 'update', 'destroy', 'confirm',  // ← thêm 'confirm'
],
```

- [ ] **Step 2: Thêm label vào $ACTION_LABELS**

```php
'confirm' => 'Xác nhận',
```

- [ ] **Step 3: Re-seed (chỉ permission, không re-seed users)**

Dùng tinker để tạo thủ công (tránh lỗi user seed):

```bash
php artisan tinker --execute="
\$parent = \App\Modules\Core\Models\Permission::where('name', 'group:task-assignment-item-reports')->first();
\App\Modules\Core\Models\Permission::firstOrCreate(
    ['name' => 'task-assignment-item-reports.confirm', 'guard_name' => 'web'],
    ['description' => 'Báo cáo công việc - Xác nhận', 'sort_order' => 5, 'parent_id' => \$parent?->id]
);
foreach (['Super Admin', 'Admin'] as \$roleName) {
    \$role = \App\Modules\Core\Models\Role::where('name', \$roleName)->first();
    \$role?->givePermissionTo('task-assignment-item-reports.confirm');
}
echo 'done';
"
```

---

## Task 11: Sync `TaskReminderStatusEnum`

**File:** `app/Modules/TaskAssignment/Enums/TaskReminderStatusEnum.php`

- [ ] **Step 1: Đổi values cho khớp migration**

Replace toàn bộ enum cases với:
```php
case Pending = 'pending';
case Fired = 'fired';
case Cancelled = 'cancelled';
```

Update `label()`:
```php
return match ($this) {
    self::Pending => 'Chờ gửi',
    self::Fired => 'Đã gửi',
    self::Cancelled => 'Đã hủy',
};
```

- [ ] **Step 2: Grep usage**

Kiểm tra có chỗ nào code khác dùng `TaskReminderStatusEnum::Sent` hay `::Failed` không:

```bash
grep -rn "TaskReminderStatusEnum::" app/ tests/ --include="*.php"
```

Nếu có chỗ nào dùng `Sent` / `Failed` → fix lại theo mapping:
- `Sent` → `Fired`
- `Failed` → `Cancelled` (hoặc remove nếu không logic nào retry fail)

---

## Task 12: Tests

### Test A: Multi-department attach

**File:** `tests/Feature/TaskAssignment/UserMultiDepartmentTest.php`

- [ ] **Viết test:**

```php
<?php

namespace Tests\Feature\TaskAssignment;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use App\Modules\TaskAssignment\Services\TaskAssignmentDepartmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMultiDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private TaskAssignmentDepartmentService $service;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        $this->service = app(TaskAssignmentDepartmentService::class);
    }

    public function test_user_can_belong_to_multiple_departments(): void
    {
        $user = User::factory()->create();
        $deptA = TaskAssignmentDepartment::create(['code' => 'A', 'name' => 'A', 'organization_id' => $this->org->id]);
        $deptB = TaskAssignmentDepartment::create(['code' => 'B', 'name' => 'B', 'organization_id' => $this->org->id]);

        $this->service->syncUsers($deptA, [$user->id]);
        $this->service->syncUsers($deptB, [$user->id]);

        $this->assertSame(2, TaskAssignmentUser::where('user_id', $user->id)->where('organization_id', $this->org->id)->count());
    }

    public function test_first_attachment_becomes_primary_automatically(): void
    {
        $user = User::factory()->create();
        $deptA = TaskAssignmentDepartment::create(['code' => 'A', 'name' => 'A', 'organization_id' => $this->org->id]);

        $this->service->syncUsers($deptA, [$user->id]);

        $record = TaskAssignmentUser::where('user_id', $user->id)->first();
        $this->assertTrue((bool) $record->is_primary);
    }

    public function test_set_primary_swaps_flag(): void
    {
        $user = User::factory()->create();
        $deptA = TaskAssignmentDepartment::create(['code' => 'A', 'name' => 'A', 'organization_id' => $this->org->id]);
        $deptB = TaskAssignmentDepartment::create(['code' => 'B', 'name' => 'B', 'organization_id' => $this->org->id]);
        $this->service->syncUsers($deptA, [$user->id]);  // A primary
        $this->service->syncUsers($deptB, [$user->id]);  // B not primary

        $this->service->setPrimary($user->id, $deptB->id);

        $recA = TaskAssignmentUser::where('user_id', $user->id)->where('task_assignment_department_id', $deptA->id)->first();
        $recB = TaskAssignmentUser::where('user_id', $user->id)->where('task_assignment_department_id', $deptB->id)->first();
        $this->assertFalse((bool) $recA->is_primary);
        $this->assertTrue((bool) $recB->is_primary);
    }

    public function test_remove_primary_promotes_another(): void
    {
        $user = User::factory()->create();
        $deptA = TaskAssignmentDepartment::create(['code' => 'A', 'name' => 'A', 'organization_id' => $this->org->id]);
        $deptB = TaskAssignmentDepartment::create(['code' => 'B', 'name' => 'B', 'organization_id' => $this->org->id]);
        $this->service->syncUsers($deptA, [$user->id]);  // A primary
        $this->service->syncUsers($deptB, [$user->id]);

        $this->service->removeUser($deptA, $user->id);

        $recB = TaskAssignmentUser::where('user_id', $user->id)->where('task_assignment_department_id', $deptB->id)->first();
        $this->assertTrue((bool) $recB->is_primary);
    }
}
```

- [ ] **Run:** `php artisan test --filter UserMultiDepartmentTest` — expect 4 pass.

### Test B: Report confirm

**File:** `tests/Feature/TaskAssignment/ReportConfirmTest.php`

- [ ] **Viết test:**

```php
<?php

namespace Tests\Feature\TaskAssignment;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportConfirmTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(): User
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        return $user;
    }

    private function makeReport(): TaskAssignmentItemReport
    {
        $org = Organization::first() ?? Organization::create(['slug' => 'x', 'name' => 'x', 'status' => 'active']);
        setPermissionsTeamId($org->id);

        $doc = TaskAssignmentDocument::create([
            'name' => 'D', 'status' => 'draft', 'organization_id' => $org->id,
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $doc->id, 'name' => 'Item',
            'deadline_type' => 'no_deadline', 'organization_id' => $org->id,
        ]);

        return TaskAssignmentItemReport::create([
            'task_assignment_item_id' => $item->id,
            'report_document_content' => 'Xong',
            'organization_id' => $org->id,
        ]);
    }

    public function test_confirm_sets_manager_confirmed_and_locks(): void
    {
        $this->setupUser();
        $report = $this->makeReport();

        $res = $this->patchJson("/api/task-assignment-item-reports/{$report->id}/confirm", [
            'confirm_note' => 'Đạt quy định',
        ]);

        $res->assertOk();
        $report->refresh();
        $this->assertTrue((bool) $report->manager_confirmed);
        $this->assertTrue((bool) $report->is_locked);
        $this->assertSame('Đạt quy định', $report->manager_confirm_note);
        $this->assertNotNull($report->manager_confirmed_at);
        $this->assertNotNull($report->locked_at);
    }

    public function test_confirm_rejects_when_already_locked(): void
    {
        $this->setupUser();
        $report = $this->makeReport();
        $report->update(['is_locked' => true, 'locked_at' => now()]);

        $res = $this->patchJson("/api/task-assignment-item-reports/{$report->id}/confirm");

        $res->assertStatus(422);
    }

    public function test_update_rejects_when_locked(): void
    {
        $this->setupUser();
        $report = $this->makeReport();
        $report->update(['is_locked' => true, 'locked_at' => now()]);

        $res = $this->patchJson("/api/task-assignment-item-reports/{$report->id}", [
            'report_document_content' => 'Sửa lại',
        ]);

        $res->assertStatus(422);
    }
}
```

- [ ] **Run:** `php artisan test --filter ReportConfirmTest` — expect 3 pass.

### Full suite

- [ ] **Run:** `php artisan test` — không regression.

---

## Task 13: Update spec gốc với correction notes

**File:** `phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md`

- [ ] **Step 1: Thêm header "Corrections" sau dòng 4**

```markdown
---

> **📝 Corrections log (2026-04-21):** Sau ~3 tháng triển khai, các điểm sau đã được điều chỉnh thực tế. Xem [`docs/superpowers/specs/2026-04-21-task-assignment-corrections.md`](docs/superpowers/specs/2026-04-21-task-assignment-corrections.md) để biết chi tiết.
>
> - **§3.1:** Tên bảng thực tế là `task_assignment_users`. Có cột `is_primary`, unique key `(user_id, task_assignment_department_id, organization_id)`. User có thể ở **nhiều phòng ban** trong cùng org.
> - **§3.7:** Code dùng tên cột `department_id` (không phải `assigned_department_id`), nhưng semantic **là snapshot** (verified) — giá trị chốt tại thời điểm giao, không cập nhật về sau. Cũng có thêm cột `department_role` (main/cooperate).
> - **§3.12:** Đã refactor. Bảng `task_assignment_reminders` hiện có FK `notification_schedule_id` → `notification_schedules`, `moment` (before/on/after), `status` (pending/fired/cancelled), `fired_at`. Bỏ channel/recipient_*/error_message.
> - **§3.13:** Bảng `task_assignment_notification_settings` **KHÔNG triển khai**. Thay bằng hệ thống Core `notification_event_configs` + `notification_schedules` (module_key=`task_assignment`, event_key=`reminder_before`/`reminder_on`/`reminder_after`).
> - **§4.4:** **Module CÓ dùng `organization_id`** vì Spatie Permission chạy teamsMode (yêu cầu `organization_id` non-null trong `model_has_roles`).
> - **§5.2:** Thống kê theo phòng ban dùng cột `department_id` (snapshot at assignment time) — semantic khớp `assigned_department_id` của spec.
> - **§6.2:** Command thực tế là `notifications:process-reminders` (chung cho Core), không có command riêng cho module.
> - **§6.5:** API cấu hình thông báo dùng endpoint Core: `/api/task-assignment/notification-config/*`. KHÔNG có `/api/task-assignment-notification-settings`.

---
```

- [ ] **Step 2: Verify markdown render OK**

Mở file, check định dạng blockquote hiển thị đúng.

---

## Final verification

- [ ] **Run full suite:** `php artisan test` → pass all.
- [ ] **Run Pint on changed PHP files:** `vendor/bin/pint app/Modules/TaskAssignment/ tests/Feature/TaskAssignment/`
- [ ] **Commit 1 lần** — gộp tất cả changes.

```bash
git add \
  database/migrations/2026_04_21_*.php \
  app/Modules/TaskAssignment/ \
  database/seeders/PermissionSeeder.php \
  tests/Feature/TaskAssignment/ \
  docs/superpowers/specs/2026-04-21-task-assignment-corrections.md \
  docs/superpowers/plans/2026-04-21-task-assignment-corrections.md \
  phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md

git commit -m "feat(task-assignment): corrections — multi-dept user + report confirm/lock + enum sync

- Multi-department per user: add is_primary, change unique to
  (user_id, task_assignment_department_id, organization_id).
  Service updates for delta-sync, setPrimary, auto-promote on remove.

- Report confirm/lock (spec §3.9): add 7 columns
  (manager_confirmed, manager_confirmed_by/at/_note,
  is_locked, locked_at/by). New endpoint
  PATCH /api/task-assignment-item-reports/{id}/confirm.
  Update/destroy reject when locked.

- Sync TaskReminderStatusEnum with migration values
  (pending/fired/cancelled). Was pending/sent/failed — stale.

- Add permission task-assignment-item-reports.confirm (Super Admin + Admin auto).

- Spec corrections log added: organization_id kept (Spatie teamsMode),
  department_id semantic = assigned_department_id (snapshot verified),
  reminder system uses Core notifications (accepted)."
```
