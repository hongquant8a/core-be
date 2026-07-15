<?php

namespace App\Modules\Core\Middleware;

use App\Modules\Core\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sau auth:sanctum: đồng bộ user sang guard web (Spatie dùng chung guard web cho API),
 * và đặt organization_id cho Spatie Permission (tính năng teams).
 */
class SetPermissionsTeamId
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();
        if ($user) {
            Auth::guard('web')->setUser($user);

            $organizationId = $this->resolveRequestedOrganizationId($request);

            if ($organizationId === null) {
                throw ValidationException::withMessages([
                    'organization_id' => ['Vui lòng gửi header X-Organization-Id để xác định tổ chức làm việc.'],
                ]);
            }

            $organization = Organization::query()
                ->whereKey($organizationId)
                ->where('status', 'active')
                ->first();

            if (! $organization) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tổ chức không hợp lệ hoặc đã ngừng hoạt động.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }

            if (! $this->userHasOrganizationAccess((int) $user->id, (int) $organization->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền truy cập tổ chức đã chọn.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }

            setPermissionsTeamId((int) $organization->id);
        }

        return $next($request);
    }

    protected function resolveRequestedOrganizationId(Request $request): ?int
    {
        $value = $request->header('X-Organization-Id')
            ?? $request->header('x-organization-id');

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    protected function userHasOrganizationAccess(int $userId, int $organizationId): bool
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'organization_id';
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $modelHasPermissionsTable = $tableNames['model_has_permissions'] ?? 'model_has_permissions';
        $modelType = (new \App\Modules\Core\Models\User)->getMorphClass();

        $hasRole = DB::table($modelHasRolesTable)
            ->where($modelMorphKey, $userId)
            ->where('model_type', $modelType)
            ->where($teamForeignKey, $organizationId)
            ->exists();

        if ($hasRole) {
            return true;
        }

        return DB::table($modelHasPermissionsTable)
            ->where($modelMorphKey, $userId)
            ->where('model_type', $modelType)
            ->where($teamForeignKey, $organizationId)
            ->exists();
    }
}
