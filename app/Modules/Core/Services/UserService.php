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
use Laravel\Sanctum\PersonalAccessToken;
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
            $profileFields = ['gender', 'birth_date', 'citizen_id', 'permanent_address', 'temporary_address', 'telegram_chat_id'];
            $profileData = array_intersect_key($data, array_flip($profileFields));

            unset($data['assignments'], $data['avatar']);
            foreach ($profileFields as $f) {
                unset($data[$f]);
            }

            $passwordChanged = isset($data['password']);
            if ($passwordChanged) {
                $data['password'] = Hash::make($data['password']);
            }

            $hasPhone = array_key_exists('phone', $data);

            $user->update($data);

            // Sửa riêng phone/profile không làm "bẩn" bảng users: 'phone' bị hook
            // saving() gỡ khỏi attributes, còn field profile đã tách sang bảng
            // khác từ trước. Model hết dirty → save() bỏ qua performUpdate →
            // event 'updating' không chạy → updated_by/updated_at đứng im dù
            // người dùng vừa sửa, khiến cột "Cập nhật" trên UI trống trơn.
            $usersRowChanged = $user->wasChanged();

            if (! empty($profileData)) {
                \App\Modules\Core\Models\UserProfile::firstOrCreate(['user_id' => $user->id])
                    ->update($profileData);
            }

            // touch() bump updated_at → model dirty trở lại → 'updating' chạy và
            // ghi updated_by như mọi thay đổi khác.
            if (! $usersRowChanged && ($hasPhone || ! empty($profileData))) {
                $user->touch();
            }

            if ($hasAssignments) {
                $this->syncUserAssignments($user, $assignments);
            }

            if ($hasAvatar) {
                $this->handleAvatar($user, $avatar);
            }

            // Admin đặt lại mật khẩu hộ → mọi phiên đang mở của user đó phải hết hiệu lực,
            // nếu không việc "reset mật khẩu vì nghi bị chiếm tài khoản" hoàn toàn vô nghĩa.
            // Trường hợp admin tự sửa chính mình thì giữ lại phiên đang thao tác.
            if ($passwordChanged) {
                $this->revokeTokens($user, request()->bearerToken());
            }

            return $user->load(['creator.media', 'editor.media', 'profile']);
        });
    }

    /**
     * Đổi mật khẩu cho chính tài khoản đang đăng nhập.
     *
     * Mật khẩu hiện tại đã được ChangePasswordRequest kiểm tra trước khi vào đây.
     * Sau khi đổi: thu hồi toàn bộ token của các phiên KHÁC (giữ token đang dùng để
     * người dùng không bị đá ra ngay) — nếu token cũ bị lộ thì đổi mật khẩu mới thực sự
     * cắt được quyền truy cập của kẻ tấn công.
     *
     * @param  string|null  $keepBearerToken  Plain-text token của request hiện tại (giữ lại phiên này).
     */
    public function changePassword(User $user, string $newPassword, ?string $keepBearerToken = null): User
    {
        return DB::transaction(function () use ($user, $newPassword, $keepBearerToken) {
            $user->forceFill(['password' => Hash::make($newPassword)])->save();

            $this->revokeTokens($user, $keepBearerToken);

            return $user;
        });
    }

    /**
     * Thu hồi token của user, giữ lại đúng token đang gọi request (nếu có).
     *
     * KHÔNG dùng $user->currentAccessToken(): middleware SetPermissionsTeamId đã
     * Auth::guard('web')->setUser(), nên Sanctum trả TransientToken thay vì PersonalAccessToken
     * thật → không biết token nào cần giữ và sẽ xoá nhầm cả phiên hiện tại. Resolve thẳng
     * từ plain-text token cho chắc chắn.
     */
    protected function revokeTokens(User $user, ?string $keepBearerToken = null): void
    {
        $tokens = $user->tokens();

        $keep = $keepBearerToken ? PersonalAccessToken::findToken($keepBearerToken) : null;
        if ($keep && (int) $keep->tokenable_id === (int) $user->id) {
            $tokens->whereKeyNot($keep->getKey());
        }

        $tokens->delete();
    }

    public function destroy(User $user): void
    {
        $this->guardActiveAssignments([$user->id]);

        DB::transaction(function () use ($user) {
            $user->taskAssignmentUser()->delete();
            $user->delete();
        });
    }

    public function bulkDestroy(array $ids): void
    {
        $this->guardActiveAssignments($ids);

        DB::transaction(function () use ($ids) {
            \App\Modules\TaskAssignment\Models\TaskAssignmentUser::whereIn('user_id', $ids)->delete();
            User::whereIn('id', $ids)->delete();
        });
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
            ->where('model_type', (new User())->getMorphClass())
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
                    'model_type' => (new User())->getMorphClass(),
                    $modelMorphKey => $user->id,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table($modelHasRolesTable)->insert($rows);
        }
    }
}
