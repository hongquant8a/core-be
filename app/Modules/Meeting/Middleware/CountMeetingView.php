<?php

namespace App\Modules\Meeting\Middleware;

use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Models\MeetingView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Đếm lượt xem chi tiết cuộc họp — không dedupe, mỗi request 2xx +1.
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
        $document = $request->route('meetingDocument');

        if ($meeting instanceof Meeting) {
            $this->logMeetingView($meeting, $request);

            return;
        }

        if ($document instanceof MeetingDocument) {
            $this->logDocumentView($document, $request);
        }
    }

    private function logMeetingView(Meeting $meeting, Request $request): void
    {
        try {
            $userId = $this->resolveUserId($request);
            DB::transaction(function () use ($meeting, $request, $userId) {
                $meeting->increment('view_count');
                MeetingView::create([
                    'organization_id' => $meeting->organization_id,
                    'meeting_id' => $meeting->id,
                    'meeting_document_id' => null,
                    'user_id' => $userId,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'viewed_at' => now(),
                    'kind' => 'meeting',
                ]);
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function logDocumentView(MeetingDocument $document, Request $request): void
    {
        try {
            // GET /meeting-documents/public/{id} = fetch metadata page, KHÔNG phải xem file.
            // File view/download đi qua endpoint /download?type=view|download riêng.
            MeetingView::create([
                'organization_id' => $document->organization_id,
                'meeting_id' => $document->meeting_id,
                'meeting_document_id' => $document->id,
                'user_id' => $this->resolveUserId($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'viewed_at' => now(),
                'kind' => 'document_meta_view',
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Resolve auth user id — public route không có middleware auth:sanctum nên
     * auth()->id() (guard web) luôn null. Phải check guard sanctum (Bearer header)
     * + cookie `accessToken` (FE SPA pattern) để không mất stats "người xem".
     */
    private function resolveUserId(Request $request): ?int
    {
        $id = auth()->id() ?? Auth::guard('sanctum')->id();
        if ($id) {
            return (int) $id;
        }
        $user = $this->resolveUserFromCookieToken($request);

        return $user ? (int) $user->id : null;
    }

    /**
     * FE SPA dùng cookie `accessToken` format Sanctum `id|plain_text`.
     * Cùng pattern với MeetingService::resolveUserFromCookieToken.
     */
    private function resolveUserFromCookieToken(Request $request): ?\App\Modules\Core\Models\User
    {
        $token = $request->cookie('accessToken');
        if (! $token || ! str_contains($token, '|')) {
            return null;
        }
        [$id, $plain] = explode('|', $token, 2);
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::find($id);
        if (! $accessToken) {
            return null;
        }
        if (! hash_equals($accessToken->token, hash('sha256', $plain))) {
            return null;
        }

        return $accessToken->tokenable instanceof \App\Modules\Core\Models\User
            ? $accessToken->tokenable
            : null;
    }
}
