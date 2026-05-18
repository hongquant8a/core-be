<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Enums\UserStatusEnum;
use App\Modules\Core\Exports\UsersExport;
use App\Modules\Core\Imports\UsersImport;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ExportFilename;
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
        return User::with(['creator.media', 'editor.media', 'profile'])->filter($filters)->paginate($limit);
    }

    /**
     * Phân bố người dùng theo tổ chức (primary org từ user_preferences.current_organization_id).
     *
     * Trả mảng {organization_id, name, total} sort theo total desc.
     * Áp dụng filter status/search như endpoint stats.
     */
    public function statsByOrganization(array $filters, int $limit = 10): array
    {
        $userIds = User::filter($filters)->pluck('id');

        $rows = DB::table('user_preferences')
            ->join('organizations', 'organizations.id', '=', 'user_preferences.current_organization_id')
            ->whereIn('user_preferences.user_id', $userIds)
            ->whereNotNull('user_preferences.current_organization_id')
            ->select(
                'organizations.id as organization_id',
                'organizations.name',
                DB::raw('count(distinct user_preferences.user_id) as total')
            )
            ->groupBy('organizations.id', 'organizations.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $totalWithOrg = $rows->sum('total');
        $others = $userIds->count() - $totalWithOrg;

        $result = $rows->map(fn ($r) => [
            'organization_id' => (int) $r->organization_id,
            'name' => $r->name,
            'total' => (int) $r->total,
        ])->all();

        if ($others > 0) {
            $result[] = [
                'organization_id' => null,
                'name' => 'Khác',
                'total' => $others,
            ];
        }

        return $result;
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

            return $user->load(['creator.media', 'editor.media', 'profile']);
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $avatar = $data['avatar'] ?? null;
            $hasAvatar = array_key_exists('avatar', $data);
            $hasAssignments = array_key_exists('assignments', $data);
            $assignments = $this->normalizeAssignments($data['assignments'] ?? []);

            // Tách profile fields: BE auto-route sang user_profiles trong cùng transaction.
            // 'phone' không tách ở đây vì model User đã có boot routing (saving/saved).
            $profileFields = ['gender', 'birth_date', 'citizen_id', 'permanent_address', 'temporary_address'];
            $profileData = array_intersect_key($data, array_flip($profileFields));

            unset($data['assignments'], $data['avatar']);
            foreach ($profileFields as $f) {
                unset($data[$f]);
            }

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            if (! empty($profileData)) {
                \App\Modules\Core\Models\UserProfile::firstOrCreate(['user_id' => $user->id])
                    ->update($profileData);
            }

            if ($hasAssignments) {
                $this->syncUserAssignments($user, $assignments);
            }

            if ($hasAvatar) {
                $this->handleAvatar($user, $avatar);
            }

            return $user->load(['creator.media', 'editor.media', 'profile']);
        });
    }

    public function destroy(User $user): void
    {
        $this->guardActiveAssignments([$user->id]);
        $user->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        $this->guardActiveAssignments($ids);
        User::destroy($ids);
    }

    /**
     * Chặn xóa user nếu họ đang được giao task chưa done/cancelled.
     * Lý do: cascadeOnDelete sẽ xóa silently các pivot row → manager mất assignee
     * mà không hay biết. Bắt buộc transfer sạch trước khi xóa.
     *
     * @param  array<int>  $userIds
     *
     * @throws ValidationException
     */
    private function guardActiveAssignments(array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        $blocked = DB::table('task_assignment_item_user as tiu')
            ->join('task_assignment_items as ti', 'ti.id', '=', 'tiu.task_assignment_item_id')
            ->whereIn('tiu.user_id', $userIds)
            ->whereIn('tiu.assignment_status', ['assigned', 'done'])
            ->whereNotIn('ti.processing_status', ['done', 'cancelled'])
            ->select('tiu.user_id', DB::raw('count(*) as task_count'))
            ->groupBy('tiu.user_id')
            ->get();

        if ($blocked->isEmpty()) {
            return;
        }

        $names = User::whereIn('id', $blocked->pluck('user_id'))->pluck('name', 'id');
        $details = $blocked->map(fn ($row) => ($names[$row->user_id] ?? "User #{$row->user_id}").": {$row->task_count} công việc")->implode('; ');

        throw ValidationException::withMessages([
            'user_id' => ["Không thể xóa user đang có công việc chưa hoàn tất. Vui lòng chuyển công việc trước. Chi tiết: {$details}"],
        ]);
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        User::whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(User $user, string $status): User
    {
        $user->update(['status' => $status]);

        return $user->load(['creator.media', 'editor.media', 'profile']);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new UsersExport($filters), ExportFilename::make('nguoi-dung'));
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
