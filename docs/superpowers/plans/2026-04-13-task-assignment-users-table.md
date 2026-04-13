# Task Assignment Users Table - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tạo bảng `task_assignment_users` để link user vào phòng ban trong module TaskAssignment, thay vì gắn `task_assignment_department_id` trực tiếp trên bảng `users`. Quản lý user ngay trong phòng ban hiện có, không tạo module mới.

**Architecture:** Thêm bảng `task_assignment_users` (user_id, department_id, organization_id, status). Mở rộng DepartmentController/Service với các endpoint nested: `GET/POST/DELETE /task-assignment-departments/{id}/users`. Xóa cột `task_assignment_department_id` cũ trên bảng `users`, cập nhật các model/service liên quan.

**Tech Stack:** Laravel 11, MySQL, Spatie Permission, Sanctum

---

## Cleanup: Revert Over-Engineered Files

Trước khi bắt đầu, cần xóa các file đã tạo sai hướng ở lượt trước và restore các file đã sửa:

**Xóa các file mới tạo không cần:**
- `app/Modules/TaskAssignment/Controllers/TaskAssignmentUserController.php`
- `app/Modules/TaskAssignment/Services/TaskAssignmentUserService.php`
- `app/Modules/TaskAssignment/Resources/TaskAssignmentUserResource.php`
- `app/Modules/TaskAssignment/Resources/TaskAssignmentUserCollection.php`
- `app/Modules/TaskAssignment/Routes/task_assignment_user.php`
- `app/Modules/TaskAssignment/Requests/StoreTaskAssignmentUserRequest.php`
- `app/Modules/TaskAssignment/Requests/UpdateTaskAssignmentUserRequest.php`
- `app/Modules/TaskAssignment/Requests/BulkStoreTaskAssignmentUserRequest.php`
- `app/Modules/TaskAssignment/Requests/BulkDestroyTaskAssignmentUserRequest.php`
- `app/Modules/TaskAssignment/Requests/ChangeStatusTaskAssignmentUserRequest.php`

**Restore về trạng thái gốc (git checkout):**
- `routes/api.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/TaskAssignmentDataSeeder.php`
- `app/Modules/Core/Models/User.php`
- `app/Modules/Core/Resources/UserResource.php`
- `app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php`
- `app/Modules/TaskAssignment/Models/TaskAssignmentDepartment.php`

**Giữ lại (sẽ sửa lại):**
- `app/Modules/TaskAssignment/Models/TaskAssignmentUser.php` (model - giữ, sẽ đơn giản hóa)
- `database/migrations/2026_04_13_000000_create_task_assignment_users_table.php` (migration - giữ, sẽ sửa)

---

## File Structure

| File | Action | Responsibility |
|------|--------|---------------|
| `database/migrations/2026_04_13_000000_create_task_assignment_users_table.php` | Sửa lại | Migration tạo bảng + migrate data + xóa cột cũ |
| `app/Modules/TaskAssignment/Models/TaskAssignmentUser.php` | Sửa lại | Model đơn giản, relationships, không cần scope filter phức tạp |
| `app/Modules/TaskAssignment/Models/TaskAssignmentDepartment.php` | Modify | Thêm relationship `taskAssignmentUsers()` |
| `app/Modules/Core/Models/User.php` | Modify | Thay `task_assignment_department_id` bằng `taskAssignmentUser` relationship |
| `app/Modules/Core/Resources/UserResource.php` | Modify | Lấy department_id qua relationship mới |
| `app/Modules/TaskAssignment/Services/TaskAssignmentDepartmentService.php` | Modify | Thêm methods: `getUsers()`, `syncUsers()`, `addUser()`, `removeUser()` |
| `app/Modules/TaskAssignment/Controllers/TaskAssignmentDepartmentController.php` | Modify | Thêm endpoints: `users()`, `syncUsers()`, `addUser()`, `removeUser()` |
| `app/Modules/TaskAssignment/Resources/DepartmentResource.php` | Modify | Thêm `users_count` |
| `app/Modules/TaskAssignment/Requests/SyncDepartmentUsersRequest.php` | Create | Validate sync/add user requests |
| `app/Modules/TaskAssignment/Routes/task_assignment_department.php` | Modify | Thêm routes user management |
| `app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php` | Modify | Update `applyDepartmentRestriction()` |
| `database/seeders/PermissionSeeder.php` | Modify | Thêm permissions mới vào group `task-assignment-departments` |
| `database/seeders/TaskAssignmentDataSeeder.php` | Modify | Dùng `TaskAssignmentUser` thay vì update trực tiếp user |

---

### Task 1: Cleanup - Revert & Remove Over-Engineered Files

**Files:**
- Remove: 10 files listed in Cleanup section
- Restore: 7 files listed in Cleanup section

- [ ] **Step 1: Xóa các file không cần**

```bash
rm app/Modules/TaskAssignment/Controllers/TaskAssignmentUserController.php
rm app/Modules/TaskAssignment/Services/TaskAssignmentUserService.php
rm app/Modules/TaskAssignment/Resources/TaskAssignmentUserResource.php
rm app/Modules/TaskAssignment/Resources/TaskAssignmentUserCollection.php
rm app/Modules/TaskAssignment/Routes/task_assignment_user.php
rm app/Modules/TaskAssignment/Requests/StoreTaskAssignmentUserRequest.php
rm app/Modules/TaskAssignment/Requests/UpdateTaskAssignmentUserRequest.php
rm app/Modules/TaskAssignment/Requests/BulkStoreTaskAssignmentUserRequest.php
rm app/Modules/TaskAssignment/Requests/BulkDestroyTaskAssignmentUserRequest.php
rm app/Modules/TaskAssignment/Requests/ChangeStatusTaskAssignmentUserRequest.php
```

- [ ] **Step 2: Restore các file đã sửa về trạng thái gốc**

```bash
git checkout -- routes/api.php
git checkout -- database/seeders/PermissionSeeder.php
git checkout -- database/seeders/TaskAssignmentDataSeeder.php
git checkout -- app/Modules/Core/Models/User.php
git checkout -- app/Modules/Core/Resources/UserResource.php
git checkout -- app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php
git checkout -- app/Modules/TaskAssignment/Models/TaskAssignmentDepartment.php
```

- [ ] **Step 3: Verify trạng thái sạch**

```bash
git status
```

Expected: Chỉ còn 2 file untracked: `TaskAssignmentUser.php` model và migration file, cộng với `api-cho-fe-mytask-dashboard.txt` và `TaskAssignmentDocument.php` (staged từ trước).

---

### Task 2: Migration - Tạo bảng `task_assignment_users`

**Files:**
- Modify: `database/migrations/2026_04_13_000000_create_task_assignment_users_table.php`

- [ ] **Step 1: Viết lại migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignment_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_assignment_department_id')->constrained('task_assignment_departments')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'organization_id']);
            $table->index(['task_assignment_department_id', 'status']);
            $table->index('organization_id');
        });

        // Migrate data cũ từ users.task_assignment_department_id sang bảng mới
        DB::statement("
            INSERT INTO task_assignment_users (user_id, task_assignment_department_id, status, organization_id, created_by, updated_by, created_at, updated_at)
            SELECT u.id, u.task_assignment_department_id, 'active', mhr.organization_id, u.id, u.id, NOW(), NOW()
            FROM users u
            INNER JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type = 'App\\\\Modules\\\\Core\\\\Models\\\\User'
            WHERE u.task_assignment_department_id IS NOT NULL
            GROUP BY u.id, u.task_assignment_department_id, mhr.organization_id
        ");

        // Xóa cột cũ trên bảng users
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_assignment_department_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('task_assignment_department_id')
                ->nullable()
                ->after('updated_by')
                ->constrained('task_assignment_departments')
                ->nullOnDelete();
        });

        DB::statement("
            UPDATE users u
            INNER JOIN task_assignment_users tau ON tau.user_id = u.id
            SET u.task_assignment_department_id = tau.task_assignment_department_id
        ");

        Schema::dropIfExists('task_assignment_users');
    }
};
```

- [ ] **Step 2: Commit**

```bash
git add database/migrations/2026_04_13_000000_create_task_assignment_users_table.php
git commit -m "feat: add task_assignment_users migration"
```

---

### Task 3: Model - TaskAssignmentUser (đơn giản)

**Files:**
- Modify: `app/Modules/TaskAssignment/Models/TaskAssignmentUser.php`

- [ ] **Step 1: Viết lại model đơn giản**

```php
<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\User;
use App\Modules\Core\Traits\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;

class TaskAssignmentUser extends Model
{
    use HasOrganizationScope;

    protected $table = 'task_assignment_users';

    protected $fillable = [
        'user_id',
        'task_assignment_department_id',
        'status',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected static function booted()
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(TaskAssignmentDepartment::class, 'task_assignment_department_id');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/TaskAssignment/Models/TaskAssignmentUser.php
git commit -m "feat: add TaskAssignmentUser model"
```

---

### Task 4: Update User model & UserResource

**Files:**
- Modify: `app/Modules/Core/Models/User.php`
- Modify: `app/Modules/Core/Resources/UserResource.php`

- [ ] **Step 1: Update User model**

Trong `app/Modules/Core/Models/User.php`:

1. Thay import `TaskAssignmentDepartment` bằng `TaskAssignmentUser`:
```php
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
```

2. Xóa `'task_assignment_department_id'` khỏi `$fillable`.

3. Thay relationship `taskAssignmentDepartment()` bằng:
```php
public function taskAssignmentUser()
{
    return $this->hasOne(TaskAssignmentUser::class, 'user_id');
}
```

4. Update filter scope - thay:
```php
->when($filters['task_assignment_department_id'] ?? null, function ($query, $deptId) {
    $query->where('task_assignment_department_id', $deptId);
})
```
bằng:
```php
->when($filters['task_assignment_department_id'] ?? null, function ($query, $deptId) {
    $query->whereHas('taskAssignmentUser', fn ($q) => $q->where('task_assignment_department_id', $deptId));
})
```

- [ ] **Step 2: Update UserResource**

Trong `app/Modules/Core/Resources/UserResource.php`, thay:
```php
'task_assignment_department_id' => $this->task_assignment_department_id,
```
bằng:
```php
'task_assignment_department_id' => $this->whenLoaded('taskAssignmentUser', fn () => $this->taskAssignmentUser?->task_assignment_department_id),
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Core/Models/User.php app/Modules/Core/Resources/UserResource.php
git commit -m "refactor: use taskAssignmentUser relationship instead of direct column"
```

---

### Task 5: Update TaskAssignmentDepartment model

**Files:**
- Modify: `app/Modules/TaskAssignment/Models/TaskAssignmentDepartment.php`

- [ ] **Step 1: Thêm relationship `taskAssignmentUsers()`**

Thêm sau method `editor()`:
```php
public function taskAssignmentUsers()
{
    return $this->hasMany(TaskAssignmentUser::class, 'task_assignment_department_id');
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/TaskAssignment/Models/TaskAssignmentDepartment.php
git commit -m "feat: add taskAssignmentUsers relationship to department"
```

---

### Task 6: Request - SyncDepartmentUsersRequest

**Files:**
- Create: `app/Modules/TaskAssignment/Requests/SyncDepartmentUsersRequest.php`

- [ ] **Step 1: Tạo request class**

```php
<?php

namespace App\Modules\TaskAssignment\Requests;

class SyncDepartmentUsersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_ids' => [
                'description' => 'Danh sách ID người dùng.',
                'example' => [1, 2, 3],
            ],
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/TaskAssignment/Requests/SyncDepartmentUsersRequest.php
git commit -m "feat: add SyncDepartmentUsersRequest"
```

---

### Task 7: Service - Thêm user management vào DepartmentService

**Files:**
- Modify: `app/Modules/TaskAssignment/Services/TaskAssignmentDepartmentService.php`

- [ ] **Step 1: Thêm import và methods**

Thêm import:
```php
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
```

Thêm 3 methods cuối class:

```php
public function getUsers(TaskAssignmentDepartment $department)
{
    return $department->taskAssignmentUsers()
        ->with('user.media')
        ->where('status', 'active')
        ->get();
}

public function syncUsers(TaskAssignmentDepartment $department, array $userIds): void
{
    $orgId = getPermissionsTeamId();

    // Xóa user cũ trong department này
    TaskAssignmentUser::where('task_assignment_department_id', $department->id)
        ->where('organization_id', $orgId)
        ->delete();

    // Thêm user mới
    foreach ($userIds as $userId) {
        TaskAssignmentUser::updateOrCreate(
            ['user_id' => $userId, 'organization_id' => $orgId],
            [
                'task_assignment_department_id' => $department->id,
                'status' => 'active',
            ]
        );
    }
}

public function removeUser(TaskAssignmentDepartment $department, int $userId): void
{
    TaskAssignmentUser::where('task_assignment_department_id', $department->id)
        ->where('user_id', $userId)
        ->delete();
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/TaskAssignment/Services/TaskAssignmentDepartmentService.php
git commit -m "feat: add user management methods to DepartmentService"
```

---

### Task 8: Controller - Thêm endpoints vào DepartmentController

**Files:**
- Modify: `app/Modules/TaskAssignment/Controllers/TaskAssignmentDepartmentController.php`

- [ ] **Step 1: Thêm import**

```php
use App\Modules\TaskAssignment\Requests\SyncDepartmentUsersRequest;
use App\Modules\Core\Resources\UserResource;
```

- [ ] **Step 2: Thêm 3 methods**

Thêm cuối class:

```php
/**
 * Danh sách người dùng trong phòng ban
 *
 * @urlParam taskAssignmentDepartment integer required ID phòng ban. Example: 1
 */
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
        ];
    }));
}

/**
 * Đồng bộ danh sách người dùng trong phòng ban
 *
 * @urlParam taskAssignmentDepartment integer required ID phòng ban. Example: 1
 * @bodyParam user_ids array required Danh sách ID người dùng. Example: [1,2,3]
 *
 * @response 200 {"success": true, "message": "Đồng bộ người dùng thành công!"}
 */
public function syncUsers(SyncDepartmentUsersRequest $request, TaskAssignmentDepartment $taskAssignmentDepartment)
{
    $this->departmentService->syncUsers($taskAssignmentDepartment, $request->user_ids);

    return $this->success(null, 'Đồng bộ người dùng thành công!');
}

/**
 * Xóa người dùng khỏi phòng ban
 *
 * @urlParam taskAssignmentDepartment integer required ID phòng ban. Example: 1
 * @urlParam userId integer required ID người dùng. Example: 1
 *
 * @response 200 {"success": true, "message": "Xóa người dùng khỏi phòng ban thành công!"}
 */
public function removeUser(TaskAssignmentDepartment $taskAssignmentDepartment, int $userId)
{
    $this->departmentService->removeUser($taskAssignmentDepartment, $userId);

    return $this->success(null, 'Xóa người dùng khỏi phòng ban thành công!');
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/TaskAssignment/Controllers/TaskAssignmentDepartmentController.php
git commit -m "feat: add user management endpoints to DepartmentController"
```

---

### Task 9: Routes - Thêm user management routes

**Files:**
- Modify: `app/Modules/TaskAssignment/Routes/task_assignment_department.php`

- [ ] **Step 1: Thêm routes cuối file**

```php
Route::get('/{taskAssignmentDepartment}/users', [TaskAssignmentDepartmentController::class, 'users'])->middleware('permission:task-assignment-departments.users,web');
Route::post('/{taskAssignmentDepartment}/users', [TaskAssignmentDepartmentController::class, 'syncUsers'])->middleware('permission:task-assignment-departments.syncUsers,web');
Route::delete('/{taskAssignmentDepartment}/users/{userId}', [TaskAssignmentDepartmentController::class, 'removeUser'])->middleware('permission:task-assignment-departments.removeUser,web');
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/TaskAssignment/Routes/task_assignment_department.php
git commit -m "feat: add department user management routes"
```

---

### Task 10: DepartmentResource - Thêm users_count

**Files:**
- Modify: `app/Modules/TaskAssignment/Resources/DepartmentResource.php`

- [ ] **Step 1: Thêm `users_count` vào resource**

Thêm sau `'sort_order'`:
```php
'users_count' => $this->whenCounted('taskAssignmentUsers'),
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/TaskAssignment/Resources/DepartmentResource.php
git commit -m "feat: add users_count to DepartmentResource"
```

---

### Task 11: PermissionSeeder - Thêm permissions mới

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`

- [ ] **Step 1: Thêm permissions mới vào group `task-assignment-departments`**

Thay:
```php
'task-assignment-departments' => [
    'stats', 'index', 'show', 'store', 'update', 'destroy',
    'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
],
```
bằng:
```php
'task-assignment-departments' => [
    'stats', 'index', 'show', 'store', 'update', 'destroy',
    'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import',
    'users', 'syncUsers', 'removeUser',
],
```

- [ ] **Step 2: Commit**

```bash
git add database/seeders/PermissionSeeder.php
git commit -m "feat: add department user management permissions"
```

---

### Task 12: Update TaskAssignmentItemService

**Files:**
- Modify: `app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php`

- [ ] **Step 1: Update `applyDepartmentRestriction()`**

Thay:
```php
private function applyDepartmentRestriction(array $filters): array
{
    $user = auth()->user();
    if (! $user->hasAnyRole(['Quản trị', 'Super Admin', 'Admin'])) {
        $filters['department_id'] = $user->task_assignment_department_id;
    }

    return $filters;
}
```
bằng:
```php
private function applyDepartmentRestriction(array $filters): array
{
    $user = auth()->user();
    if (! $user->hasAnyRole(['Quản trị', 'Super Admin', 'Admin'])) {
        $taskAssignmentUser = $user->taskAssignmentUser;
        $filters['department_id'] = $taskAssignmentUser?->task_assignment_department_id;
    }

    return $filters;
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php
git commit -m "refactor: use taskAssignmentUser relationship in department restriction"
```

---

### Task 13: Update TaskAssignmentDataSeeder

**Files:**
- Modify: `database/seeders/TaskAssignmentDataSeeder.php`

- [ ] **Step 1: Thêm imports**

Thêm:
```php
use App\Modules\Core\Models\Organization;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
```

- [ ] **Step 2: Update method `assignUsersToDepartments()`**

Thay:
```php
protected function assignUsersToDepartments(): void
{
    $this->deptIds = TaskAssignmentDepartment::orderBy('sort_order')->pluck('id', 'code')->all();
    $users = User::orderBy('id')->get();

    $deptCodes = array_keys($this->deptIds);
    foreach ($users as $i => $user) {
        $code = $deptCodes[$i % count($deptCodes)];
        $user->update(['task_assignment_department_id' => $this->deptIds[$code]]);
    }

    $this->usersByDept = User::whereNotNull('task_assignment_department_id')
        ->get()->groupBy('task_assignment_department_id');
```
bằng:
```php
protected function assignUsersToDepartments(): void
{
    $this->deptIds = TaskAssignmentDepartment::orderBy('sort_order')->pluck('id', 'code')->all();
    $users = User::orderBy('id')->get();
    $orgId = getPermissionsTeamId() ?: Organization::first()?->id;

    $deptCodes = array_keys($this->deptIds);
    foreach ($users as $i => $user) {
        $code = $deptCodes[$i % count($deptCodes)];
        TaskAssignmentUser::updateOrCreate(
            ['user_id' => $user->id, 'organization_id' => $orgId],
            [
                'task_assignment_department_id' => $this->deptIds[$code],
                'status' => 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );
    }

    $this->usersByDept = TaskAssignmentUser::with('user')
        ->where('organization_id', $orgId)
        ->get()
        ->groupBy('task_assignment_department_id')
        ->map(fn ($items) => $items->pluck('user'));
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/TaskAssignmentDataSeeder.php
git commit -m "refactor: use TaskAssignmentUser in data seeder"
```

---

## API Endpoints (kết quả cuối cùng)

Thêm 3 endpoints nested trong phòng ban:

| Method | URL | Mô tả | Permission |
|--------|-----|--------|------------|
| GET | `/api/task-assignment-departments/{id}/users` | Danh sách user trong phòng ban | `task-assignment-departments.users` |
| POST | `/api/task-assignment-departments/{id}/users` | Đồng bộ user vào phòng ban (gửi `user_ids`) | `task-assignment-departments.syncUsers` |
| DELETE | `/api/task-assignment-departments/{id}/users/{userId}` | Xóa 1 user khỏi phòng ban | `task-assignment-departments.removeUser` |

FE workflow:
1. Fetch all users: `GET /api/users` (endpoint hiện có)
2. Xem user trong phòng ban: `GET /api/task-assignment-departments/{id}/users`
3. Thêm/đồng bộ user: `POST /api/task-assignment-departments/{id}/users` với body `{ "user_ids": [1, 2, 3] }`
4. Xóa 1 user: `DELETE /api/task-assignment-departments/{id}/users/{userId}`
