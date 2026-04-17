<?php

namespace App\Modules\Core\Middleware;

use App\Services\Notification\Enums\NotificationModuleEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gán module_key vào request context. Controller/logic đọc qua
 * $request->attributes->get('notification_module_key').
 * Validate moduleKey có trong NotificationModuleEnum.
 *
 * Usage: middleware('notification.module:task_assignment')
 */
class SetNotificationModule
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        if (! in_array($moduleKey, NotificationModuleEnum::values(), true)) {
            abort(500, "Module notification không hợp lệ: {$moduleKey}");
        }

        $request->attributes->set('notification_module_key', $moduleKey);

        return $next($request);
    }
}
