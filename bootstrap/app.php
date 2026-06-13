<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    // Broadcasting auth route phải dùng Sanctum (project là API-first, FE gửi Bearer
    // token trong header). Default Laravel auto-register /broadcasting/auth với
    // middleware 'web' (session+CSRF) — không phù hợp Sanctum SPA. Override prefix
    // 'api' + middleware ['api', 'auth:sanctum'] để FE Echo authEndpoint trỏ tới
    // /api/broadcasting/auth.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']]
    )
    ->withCommands([
        \App\Services\Notification\Console\ProcessRemindersCommand::class,
        \App\Console\Commands\CleanupObsoleteSeedsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Mặc định Laravel set Authenticate::redirectUsing → route('login'), không tồn tại
        // trong app API-only → RouteNotFoundException 500. Override: api/* không redirect,
        // để Laravel throw AuthenticationException → render thành JSON 401.
        $middleware->redirectGuestsTo(function ($request) {
            return $request->is('api/*') ? null : '/login';
        });

        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'set.permissions.team' => \App\Modules\Core\Middleware\SetPermissionsTeamId::class,
            'log.activity' => \App\Modules\Core\Middleware\LogActivity::class,
            'ensure.route.org' => \App\Modules\Core\Middleware\EnsureRouteModelsBelongToOrganization::class,
            'sync.fcm.token' => \App\Modules\Core\Middleware\SyncFcmToken::class,
            'notification.module' => \App\Modules\Core\Middleware\SetNotificationModule::class,
            'count.meeting.view' => \App\Modules\Meeting\Middleware\CountMeetingView::class,
            'schedule.module' => \App\Modules\Scheduling\Middleware\CheckScheduleModulePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Mọi request /api/* → Laravel default render JSON cho mọi exception (kể cả
        // AuthenticationException → 401 thay vì redirect login → RouteNotFoundException).
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                    'code' => 'VALIDATION_ERROR',
                ], 422);
            }
        });
        $exceptions->renderable(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                // Spatie default message English ("User does not have the right permissions") — override sang tiếng Việt.
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền thực hiện thao tác này.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        });
        // API request thiếu/hỏng token → 401 JSON thay vì redirect tới route('login')
        // (Laravel default thử redirect → RouteNotFoundException nếu app không có web login).
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Bạn cần đăng nhập để truy cập tài nguyên này.',
                    'code' => 'UNAUTHENTICATED',
                ], 401);
            }
        });
    })->create();
