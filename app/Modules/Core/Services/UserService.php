<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Enums\UserStatusEnum;
use App\Modules\Core\Exports\UsersExport;
use App\Modules\Core\Imports\UsersImport;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserService
{
    public function __construct(private MediaService $mediaService) {}
    public function stats(array $filters): array
    {
        $base = User::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', UserStatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', '!=', UserStatusEnum::Active->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return User::with(['creator', 'editor'])->filter($filters)->paginate($limit);
    }

    public function store(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $avatar = $data['avatar'] ?? null;
            $assignments = $this->normalizeAssignments($data['assignments'] ?? []);
            unset($data['assignments'], $data['avatar']);
            $data['password'] = Hash::make($data['password']);

            $user = User::create($data);
            $this->syncUserAssignments($user, $assignments);
            $this->handleAvatar($user, $avatar);

            return $user->load(['creator', 'editor']);
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $avatar = $data['avatar'] ?? null;
            $hasAvatar = array_key_exists('avatar', $data);
            $hasAssignments = array_key_exists('assignments', $data);
            $assignments = $this->normalizeAssignments($data['assignments'] ?? []);
            unset($data['assignments'], $data['avatar']);

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            if ($hasAssignments) {
                $this->syncUserAssignments($user, $assignments);
            }

            if ($hasAvatar) {
                $this->handleAvatar($user, $avatar);
            }

            return $user->load(['creator', 'editor']);
        });
    }

    public function destroy(User $user): void
    {
        $user->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        User::destroy($ids);
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        User::whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(User $user, string $status): User
    {
        $user->update(['status' => $status]);

        return $user->load(['creator', 'editor']);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new UsersExport($filters), 'users.xlsx');
    }

    public function import($file): void
    {
        Excel::import(new UsersImport, $file);
    }

    /**
     * Chuẩn hóa payload assignments:
     * [
     *   ['role_id' => 1, 'organization_ids' => [2,3]],
     *   ['role_id' => 5, 'organization_ids' => [9]],
     * ]
     * => [organization_id => [role_id, ...]]
     */
    protected function normalizeAssignments(array $assignments): array
    {
        $map = [];
        $roleIds = collect($assignments)
            ->pluck('role_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $roles = Role::query()
            ->whereIn('id', $roleIds)
            ->get()
            ->keyBy('id');

        foreach ($assignments as $assignment) {
            $roleId = (int) ($assignment['role_id'] ?? 0);
            $organizationIds = collect($assignment['organization_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $role = $roles->get($roleId);
            if (! $role) {
                throw ValidationException::withMessages([
                    'assignments' => ["Vai trò #{$roleId} không tồn tại."],
                ]);
            }

            foreach ($organizationIds as $organizationId) {
                // Tương thích ngược: nếu role còn gắn organization_id thì phải khớp tổ chức được gán.
                if (isset($role->organization_id) && $role->organization_id !== null && (int) $role->organization_id !== $organizationId) {
                    throw ValidationException::withMessages([
                        'assignments' => ["Vai trò '{$role->name}' chỉ áp dụng cho tổ chức #{$role->organization_id}, không thể gán cho tổ chức #{$organizationId}."],
                    ]);
                }

                $map[$organizationId] ??= [];
                $map[$organizationId][] = $roleId;
            }
        }

        foreach ($map as $organizationId => $orgRoleIds) {
            $map[$organizationId] = array_values(array_unique($orgRoleIds));
        }

        return $map;
    }

    protected function handleAvatar(User $user, mixed $value): void
    {
        if ($value instanceof UploadedFile) {
            $user->clearMediaCollection('avatars');
            $this->mediaService->uploadOne($user, $value, 'avatars', ['disk' => 'public']);
        } elseif ($value === null || $value === '') {
            $user->clearMediaCollection('avatars');
        }
        // string URL → giữ nguyên, không xử lý
    }

    protected function syncUserAssignments(User $user, array $assignments): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'organization_id';

        DB::table($modelHasRolesTable)
            ->where($modelMorphKey, $user->id)
            ->where('model_type', User::class)
            ->delete();

        if (empty($assignments)) {
            return;
        }

        $rows = [];
        foreach ($assignments as $organizationId => $roleIds) {
            foreach ($roleIds as $roleId) {
                $rows[] = [
                    $teamForeignKey => (int) $organizationId,
                    $rolePivotKey => (int) $roleId,
                    'model_type' => User::class,
                    $modelMorphKey => $user->id,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table($modelHasRolesTable)->insert($rows);
        }
    }
}
