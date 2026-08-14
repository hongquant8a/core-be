<?php

namespace App\Modules\Core\Middleware;

use App\Modules\Core\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dành cho route public (guest, không auth:sanctum): nếu client gửi header
 * X-Organization-Id, đặt team context cho Spatie Permission để HasOrganizationScope
 * lọc dữ liệu theo đúng tổ chức đó. Không gửi header → giữ nguyên hành vi cũ (không lọc).
 */
class SetPublicPermissionsTeamId
{
    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = $this->resolveRequestedOrganizationId($request);

        if ($organizationId !== null) {
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

            setPermissionsTeamId((int) $organization->id);
        }

        return $next($request);
    }

    protected function resolveRequestedOrganizationId(Request $request): ?int
    {
        $value = $request->header('X-Organization-Id')
            ?? $request->header('x-organization-id');

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
