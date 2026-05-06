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
 * `handle()` sau khi controller trả response (không dùng `terminate()` vì một số setup
 * PHP-FPM không trigger reliably). Counter là metric phụ — fail không làm hỏng request chính.
 */
class CountMeetingView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Chỉ count khi response 2xx (không count 404/403/422).
        if ($response->isSuccessful()) {
            $this->trackView($request);
        }

        return $response;
    }

    private function trackView(Request $request): void
    {
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
            report($e);
        }
    }
}
