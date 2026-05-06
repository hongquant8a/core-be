<?php

namespace App\Modules\Meeting\Middleware;

use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Đếm lượt xem chi tiết cuộc họp với dedupe per (user_id, day) hoặc (ip, day) cho khách.
 *
 * Apply trên route `GET /meetings/{meeting}` + `GET /meetings/public/{meeting}`. Chạy ở
 * `terminate()` sau khi response trả về thành công (2xx) để không count khi 404/403.
 */
class CountMeetingView
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return;
        }

        $meeting = $request->route('meeting');
        if (! $meeting instanceof Meeting) {
            return;
        }

        $userId = auth()->id();
        $ip = $request->ip();

        try {
            $alreadyViewed = MeetingView::query()
                ->where('meeting_id', $meeting->id)
                ->whereNull('meeting_document_id')
                ->whereDate('viewed_at', today())
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->when(! $userId, fn ($q) => $q->whereNull('user_id')->where('ip_address', $ip))
                ->exists();

            if ($alreadyViewed) {
                return;
            }

            DB::transaction(function () use ($meeting, $userId, $ip, $request) {
                $meeting->increment('view_count');
                MeetingView::create([
                    'meeting_id' => $meeting->id,
                    'meeting_document_id' => null,
                    'user_id' => $userId,
                    'ip_address' => $ip,
                    'user_agent' => $request->userAgent(),
                    'viewed_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            // Counter là metric phụ — fail không làm hỏng request chính.
            report($e);
        }
    }
}
