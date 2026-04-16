<?php

namespace App\Modules\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncFcmToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $token = $request->header('X-FCM-Token');
        $user = $request->user();

        if ($token && $user && $user->fcm_token !== $token) {
            $user->updateQuietly(['fcm_token' => $token]);
        }

        return $response;
    }
}
