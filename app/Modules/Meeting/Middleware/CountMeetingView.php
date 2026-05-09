<?php

namespace App\Modules\Meeting\Middleware;

use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Models\MeetingView;
use Closure;
use Illuminate\Http\Request;
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
            DB::transaction(function () use ($meeting, $request) {
                $meeting->increment('view_count');
                MeetingView::create([
                    'meeting_id' => $meeting->id,
                    'meeting_document_id' => null,
                    'user_id' => auth()->id(),
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
                'meeting_id' => $document->meeting_id,
                'meeting_document_id' => $document->id,
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'viewed_at' => now(),
                'kind' => 'document_meta_view',
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
