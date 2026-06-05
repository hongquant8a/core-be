<?php

namespace App\Modules\Scheduling\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class CheckScheduleModulePermission
{
    protected const MODULE_MAP = [
        'EXECUTIVE' => 'schedules-executive',
        'OFFICE'    => 'schedules-office',
    ];

    public function handle(Request $request, Closure $next, string $action): Response
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::user();

        if (!$user) {
            throw UnauthorizedException::forPermissions(["schedules-*.*"]);
        }

        $moduleType = $request->input('module_type') ?? $request->query('module_type');

        if (!$moduleType) {
            throw ValidationException::withMessages([
                'module_type' => ['Thiếu module_type để xác định phân hệ (EXECUTIVE hoặc OFFICE).'],
            ]);
        }

        $moduleType = strtoupper($moduleType);

        if (!isset(static::MODULE_MAP[$moduleType])) {
            throw ValidationException::withMessages([
                'module_type' => ['module_type không hợp lệ. Chấp nhận: EXECUTIVE, OFFICE.'],
            ]);
        }

        $resource = static::MODULE_MAP[$moduleType];
        $newPermission = "{$resource}.{$action}";
        $oldPermission = "schedules.{$action}";

        if ($user->hasPermissionTo($newPermission) || $user->hasPermissionTo($oldPermission)) {
            return $next($request);
        }

        throw UnauthorizedException::forPermissions([$newPermission]);
    }
}
