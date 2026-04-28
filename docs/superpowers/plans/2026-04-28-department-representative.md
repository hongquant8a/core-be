# Department Representative Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add per-department `is_representative` flag so the frontend can auto-fill the assignee when picking a department for a task.

**Architecture:** New boolean column on `task_assignment_users`, write-side handled by extending the existing sync endpoint with an optional `representative_user_id`, read-side exposed through the existing `GET /users` endpoint. Application-level invariant ensures at most one rep per `(department, organization)`.

**Tech Stack:** Laravel 11, MySQL, PHPUnit, Spatie Permissions, Sanctum.

**User preferences:**
- Single commit at the end (per `feedback_fewer_commits.md`).
- No `Co-Authored-By` (per `feedback_no_coauthor.md`).

---

## File Structure

| Path | Action | Responsibility |
|------|--------|----------------|
| `database/migrations/2026_04_28_000000_add_is_representative_to_task_assignment_users.php` | Create | Add boolean column + index |
| `app/Modules/TaskAssignment/Models/TaskAssignmentUser.php` | Modify | Fillable + boolean cast |
| `app/Modules/TaskAssignment/Requests/SyncDepartmentUsersRequest.php` | Modify | Add validation rule for `representative_user_id` |
| `app/Modules/TaskAssignment/Services/TaskAssignmentDepartmentService.php` | Modify | `syncUsers` extends signature; new private `setRepresentative` |
| `app/Modules/TaskAssignment/Controllers/TaskAssignmentDepartmentController.php` | Modify | `users()` response includes flag; `syncUsers()` passes new arg |
| `tests/Feature/TaskAssignment/DepartmentRepresentativeTest.php` | Create | 9 feature tests |
| `docs/superpowers/specs/2026-04-28-department-representative-design.md` | Already exists | Design spec |
| `docs/superpowers/plans/2026-04-28-department-representative.md` | Created here | This plan |

---

## Task 1: Migration

**Files:**
- Create: `database/migrations/2026_04_28_000000_add_is_representative_to_task_assignment_users.php`

- [ ] **Step 1: Create the migration file**

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
            $table->boolean('is_representative')->default(false)->after('is_primary');
            $table->index(['task_assignment_department_id', 'is_representative'], 'ta_users_dept_rep_index');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_users', function (Blueprint $table) {
            $table->dropIndex('ta_users_dept_rep_index');
            $table->dropColumn('is_representative');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_04_28_000000_add_is_representative_to_task_assignment_users` then `Migrated`. No errors.

- [ ] **Step 3: Verify column exists with default false**

```bash
php artisan tinker --execute="echo \Illuminate\Support\Facades\Schema::hasColumn('task_assignment_users', 'is_representative') ? 'OK' : 'MISSING';"
```

Expected: `OK`

---

## Task 2: Model — fillable + cast

**Files:**
- Modify: `app/Modules/TaskAssignment/Models/TaskAssignmentUser.php`

- [ ] **Step 1: Add `is_representative` to `$fillable`**

Locate the `protected $fillable` array. Replace it with:

```php
    protected $fillable = [
        'user_id',
        'task_assignment_department_id',
        'is_primary',
        'is_representative',
        'status',
        'organization_id',
        'created_by',
        'updated_by',
    ];
```

- [ ] **Step 2: Add boolean cast for `is_representative`**

Locate the `protected $casts` array. Replace it with:

```php
    protected $casts = [
        'is_primary' => 'boolean',
        'is_representative' => 'boolean',
    ];
```

- [ ] **Step 3: Smoke-verify**

```bash
php artisan tinker --execute="echo json_encode((new \App\Modules\TaskAssignment\Models\TaskAssignmentUser())->getFillable());"
```

Expected: array contains both `is_primary` and `is_representative`.

---

## Task 3: Request validator extend

**Files:**
- Modify: `app/Modules/TaskAssignment/Requests/SyncDepartmentUsersRequest.php`

- [ ] **Step 1: Add `representative_user_id` rule**

Replace the entire file contents with:

```php
<?php

namespace App\Modules\TaskAssignment\Requests;

use Illuminate\Validation\Rule;

class SyncDepartmentUsersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'representative_user_id' => ['nullable', 'integer', Rule::in($this->input('user_ids', []))],
        ];
    }

    public function messages(): array
    {
        return [
            'representative_user_id.in' => 'Người đại diện phải nằm trong danh sách thành viên được chọn.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_ids' => [
                'description' => 'Danh sách ID người dùng.',
                'example' => [1, 2, 3],
            ],
            'representative_user_id' => [
                'description' => 'ID người đại diện của phòng ban (phải nằm trong user_ids). Nullable.',
                'example' => 2,
            ],
        ];
    }
}
```

- [ ] **Step 2: Smoke-verify validator behavior**

```bash
php artisan tinker --execute="
\$v = \Validator::make(
  ['user_ids' => [1, 2, 3], 'representative_user_id' => 99],
  (new \App\Modules\TaskAssignment\Requests\SyncDepartmentUsersRequest())->rules()
);
echo \$v->fails() ? 'FAILS_AS_EXPECTED' : 'BUG_PASSED';"
```

Expected: `FAILS_AS_EXPECTED`

(Note: `exists:users,id` on `user_ids.*` may also fail because IDs 1/2/3 may not be real users — that's fine, we only care that validation fails. If `users` table is empty in dev, the test still passes because `representative_user_id.in` also fails.)

---

## Task 4: Service — syncUsers extension + setRepresentative

**Files:**
- Modify: `app/Modules/TaskAssignment/Services/TaskAssignmentDepartmentService.php`

- [ ] **Step 1: Add use statement for ValidationException**

At the top of the file, add (alphabetically with other `use` statements):

```php
use Illuminate\Validation\ValidationException;
```

- [ ] **Step 2: Replace `syncUsers` method body**

Locate the existing `public function syncUsers(...)` method. Replace it ENTIRELY with:

```php
    public function syncUsers(TaskAssignmentDepartment $department, array $userIds, ?int $representativeUserId = null): void
    {
        $orgId = getPermissionsTeamId();

        \Illuminate\Support\Facades\DB::transaction(function () use ($department, $userIds, $representativeUserId, $orgId) {
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
                    'is_primary' => ! $hasPrimary,
                ]);
            }

            if ($representativeUserId !== null) {
                $this->setRepresentative($department, $representativeUserId);
            }
        });
    }
```

- [ ] **Step 3: Add `setRepresentative` private method**

Insert this method right after `syncUsers` (before `setPrimary`):

```php
    private function setRepresentative(TaskAssignmentDepartment $department, ?int $userId): void
    {
        $orgId = getPermissionsTeamId();

        if ($userId !== null) {
            $exists = TaskAssignmentUser::where('task_assignment_department_id', $department->id)
                ->where('user_id', $userId)
                ->where('organization_id', $orgId)
                ->exists();
            if (! $exists) {
                throw ValidationException::withMessages([
                    'representative_user_id' => 'Người đại diện phải thuộc danh sách thành viên.',
                ]);
            }
        }

        TaskAssignmentUser::where('task_assignment_department_id', $department->id)
            ->where('organization_id', $orgId)
            ->update(['is_representative' => false]);

        if ($userId !== null) {
            TaskAssignmentUser::where('task_assignment_department_id', $department->id)
                ->where('user_id', $userId)
                ->where('organization_id', $orgId)
                ->update(['is_representative' => true]);
        }
    }
```

- [ ] **Step 4: Verify backward-compat by running existing tests**

```bash
php artisan test --filter=UserMultiDepartmentTest
```

Expected: 4/4 tests pass. (These tests call `syncUsers($dept, [$user->id])` without the new arg — must still work.)

---

## Task 5: Controller — users() response + syncUsers() pass-through

**Files:**
- Modify: `app/Modules/TaskAssignment/Controllers/TaskAssignmentDepartmentController.php`

- [ ] **Step 1: Add `is_representative` to the `users()` response mapper**

Locate the `users()` method (around line 279). Replace its body with:

```php
    public function users(TaskAssignmentDepartment $taskAssignmentDepartment)
    {
        $items = $this->departmentService->getUsers($taskAssignmentDepartment);

        return $this->success($items->map(function ($tau) {
            $user = $tau->user;
            $avatar = $user->getFirstMedia('avatars');

            return [
                'id' => $tau->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_name' => $user->user_name,
                'avatar' => $avatar ? '/storage/'.$avatar->id.'/'.$avatar->file_name : null,
                'status' => $tau->status,
                'is_representative' => (bool) $tau->is_representative,
            ];
        }));
    }
```

- [ ] **Step 2: Pass `representative_user_id` through `syncUsers()`**

Locate the `syncUsers()` controller method (around line 307). Replace its body with:

```php
    public function syncUsers(SyncDepartmentUsersRequest $request, TaskAssignmentDepartment $taskAssignmentDepartment)
    {
        $this->departmentService->syncUsers(
            $taskAssignmentDepartment,
            $request->user_ids,
            $request->input('representative_user_id'),
        );

        return $this->success(null, 'Đồng bộ người dùng thành công!');
    }
```

- [ ] **Step 3: Update the docblock for `syncUsers()` to document the new param**

Locate the docblock above the `syncUsers()` method. Replace the docblock with:

```php
    /**
     * Đồng bộ danh sách người dùng trong phòng ban
     *
     * @urlParam taskAssignmentDepartment integer required ID phòng ban. Example: 1
     * @bodyParam user_ids array required Danh sách ID người dùng. Example: [1,2,3]
     * @bodyParam representative_user_id integer ID người đại diện (phải nằm trong user_ids). Example: 2
     *
     * @response 200 {"success": true, "message": "Đồng bộ người dùng thành công!"}
     */
```

---

## Task 6: Feature tests

**Files:**
- Create: `tests/Feature/TaskAssignment/DepartmentRepresentativeTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

namespace Tests\Feature\TaskAssignment;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use App\Modules\TaskAssignment\Requests\SyncDepartmentUsersRequest;
use App\Modules\TaskAssignment\Services\TaskAssignmentDepartmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentRepresentativeTest extends TestCase
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

    private function makeDept(string $code = 'A'): TaskAssignmentDepartment
    {
        return TaskAssignmentDepartment::create([
            'code' => $code,
            'name' => $code,
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_sync_users_without_representative_does_not_set_any_rep(): void
    {
        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->service->syncUsers($dept, [$u1->id, $u2->id]);

        $this->assertSame(0, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->count());
    }

    public function test_sync_users_with_representative_sets_flag_correctly(): void
    {
        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();

        $this->service->syncUsers($dept, [$u1->id, $u2->id, $u3->id], $u2->id);

        $reps = TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->pluck('user_id')
            ->all();
        $this->assertSame([$u2->id], $reps);

        $nonReps = TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', false)
            ->pluck('user_id')
            ->sort()
            ->values()
            ->all();
        $expected = collect([$u1->id, $u3->id])->sort()->values()->all();
        $this->assertSame($expected, $nonReps);
    }

    public function test_sync_users_with_rep_not_in_user_ids_fails_validation(): void
    {
        $request = new SyncDepartmentUsersRequest();
        $request->merge([
            'user_ids' => [1, 2, 3],
            'representative_user_id' => 99,
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('representative_user_id', $validator->errors()->toArray());
        $this->assertSame(
            'Người đại diện phải nằm trong danh sách thành viên được chọn.',
            $validator->errors()->first('representative_user_id'),
        );
    }

    public function test_sync_users_switching_representative_clears_old_rep(): void
    {
        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->service->syncUsers($dept, [$u1->id, $u2->id], $u1->id);
        $this->assertSame(1, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->where('user_id', $u1->id)
            ->count());

        $this->service->syncUsers($dept, [$u1->id, $u2->id], $u2->id);

        $reps = TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->pluck('user_id')
            ->all();
        $this->assertSame([$u2->id], $reps);
    }

    public function test_get_users_includes_is_representative_field(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $this->service->syncUsers($dept, [$u1->id, $u2->id], $u2->id);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson("/api/task-assignment-departments/{$dept->id}/users");

        $res->assertOk();
        $items = collect($res->json('data'));
        $this->assertCount(2, $items);
        $rep = $items->firstWhere('user_id', $u2->id);
        $non = $items->firstWhere('user_id', $u1->id);
        $this->assertTrue($rep['is_representative']);
        $this->assertFalse($non['is_representative']);
    }

    public function test_remove_user_who_is_rep_leaves_department_without_rep(): void
    {
        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $this->service->syncUsers($dept, [$u1->id, $u2->id], $u2->id);

        $this->service->removeUser($dept, $u2->id);

        $this->assertSame(0, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->count());
        $this->assertSame(1, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)->count());
    }

    public function test_sync_excluding_current_rep_clears_rep(): void
    {
        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $this->service->syncUsers($dept, [$u1->id, $u2->id], $u2->id);

        // Re-sync without u2 — u2 row deleted, no rep remains
        $this->service->syncUsers($dept, [$u1->id]);

        $this->assertSame(0, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->count());
        $this->assertSame(1, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)->count());
    }

    public function test_rep_flag_isolated_per_organization(): void
    {
        $orgB = Organization::firstOrCreate(['slug' => 'org-b'], ['name' => 'Org B', 'status' => 'active']);

        // Setup org A: dept with rep
        $deptA = $this->makeDept('A');
        $userA = User::factory()->create();
        $this->service->syncUsers($deptA, [$userA->id], $userA->id);

        // Switch to org B and create another dept + user
        setPermissionsTeamId($orgB->id);
        $deptB = TaskAssignmentDepartment::create(['code' => 'B', 'name' => 'B', 'organization_id' => $orgB->id]);
        $userB = User::factory()->create();
        $this->service->syncUsers($deptB, [$userB->id], $userB->id);

        // Verify org B has its own rep
        $repB = TaskAssignmentUser::where('organization_id', $orgB->id)
            ->where('is_representative', true)
            ->first();
        $this->assertNotNull($repB);
        $this->assertSame($userB->id, $repB->user_id);

        // Verify org A's rep was untouched
        $repA = TaskAssignmentUser::where('organization_id', $this->org->id)
            ->where('is_representative', true)
            ->first();
        $this->assertNotNull($repA);
        $this->assertSame($userA->id, $repA->user_id);
    }

    public function test_rep_flag_independent_of_is_primary(): void
    {
        $dept = $this->makeDept();
        $u = User::factory()->create();

        $this->service->syncUsers($dept, [$u->id], $u->id);

        $row = TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('user_id', $u->id)
            ->first();
        $this->assertTrue((bool) $row->is_primary, 'first member auto-marked is_primary');
        $this->assertTrue((bool) $row->is_representative, 'rep flag set explicitly');
    }
}
```

- [ ] **Step 2: Run the new tests**

```bash
php artisan test --filter=DepartmentRepresentativeTest
```

Expected: 9/9 tests pass.

If `test_get_users_includes_is_representative_field` fails with 401/403, double-check that `Sanctum::actingAs($admin)` and `assignRole('Super Admin')` are sufficient permission. The existing `NotificationConfigControllerTest.php` uses this exact pattern.

If any test fails, debug — do not skip or weaken assertions.

---

## Task 7: Run full suite

- [ ] **Step 1: Run the full test suite**

```bash
php artisan test
```

Expected: all tests pass (existing 246 + 9 new = 255 tests, all green).

If a previously-green test fails, investigate before proceeding. The most likely break point is a test that depends on `syncUsers` signature — but this should be fine because the new param has a default value.

- [ ] **Step 2: Confirm `git status` shows expected files**

```bash
git status
```

Expected files:
- New: `database/migrations/2026_04_28_000000_add_is_representative_to_task_assignment_users.php`
- New: `tests/Feature/TaskAssignment/DepartmentRepresentativeTest.php`
- New: `docs/superpowers/specs/2026-04-28-department-representative-design.md`
- New: `docs/superpowers/plans/2026-04-28-department-representative.md`
- Modified: `app/Modules/TaskAssignment/Models/TaskAssignmentUser.php`
- Modified: `app/Modules/TaskAssignment/Requests/SyncDepartmentUsersRequest.php`
- Modified: `app/Modules/TaskAssignment/Services/TaskAssignmentDepartmentService.php`
- Modified: `app/Modules/TaskAssignment/Controllers/TaskAssignmentDepartmentController.php`

---

## Task 8: Final commit

- [ ] **Step 1: Stage files explicitly**

```bash
git add database/migrations/2026_04_28_000000_add_is_representative_to_task_assignment_users.php
git add app/Modules/TaskAssignment/Models/TaskAssignmentUser.php
git add app/Modules/TaskAssignment/Requests/SyncDepartmentUsersRequest.php
git add app/Modules/TaskAssignment/Services/TaskAssignmentDepartmentService.php
git add app/Modules/TaskAssignment/Controllers/TaskAssignmentDepartmentController.php
git add tests/Feature/TaskAssignment/DepartmentRepresentativeTest.php
git add docs/superpowers/specs/2026-04-28-department-representative-design.md
git add docs/superpowers/plans/2026-04-28-department-representative.md
```

- [ ] **Step 2: Commit (no Co-Authored-By per user preference)**

```bash
git commit -m "$(cat <<'EOF'
feat(task-assignment): add per-department representative flag

- Add is_representative boolean column on task_assignment_users
- Extend POST /departments/{id}/users with optional representative_user_id (must be in user_ids)
- GET /departments/{id}/users returns is_representative per item for FE auto-fill
- Application-level invariant: at most one rep per (department, organization), enforced in service transaction
- 9 feature tests cover sync/switch/remove/multi-org/independence-from-is_primary
EOF
)"
```

- [ ] **Step 3: Verify clean tree**

```bash
git status
```

Expected: `nothing to commit, working tree clean`.

---

## Self-Review Notes

**Spec coverage (each spec section maps to a task):**
- Migration / DB column → Task 1 ✓
- Model fillable + cast → Task 2 ✓
- Request validation rule + messages → Task 3 ✓
- Service `syncUsers` extended signature, new private `setRepresentative` → Task 4 ✓
- Controller `users()` response, `syncUsers()` pass-through → Task 5 ✓
- 9 feature tests including edge cases (multi-org, sync-without-rep, validation rejection, switch, remove, FE response shape, independence from `is_primary`) → Task 6 ✓
- Single commit at end → Task 8 ✓

**Type/method consistency:**
- `setRepresentative(TaskAssignmentDepartment $department, ?int $userId): void` declared identically in spec, Task 4, and Task 6.
- `syncUsers(TaskAssignmentDepartment $department, array $userIds, ?int $representativeUserId = null): void` consistent across spec, Task 4, controller call site, and tests.
- Validation message string `Người đại diện phải nằm trong danh sách thành viên được chọn.` consistent in Request (Task 3) and test assertion (Task 6).

**No placeholders:** every step has either complete code, exact commands with expected output, or clear pass/fail criteria.
