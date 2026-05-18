<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Models\MeetingView;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Xuất tổng hợp tài liệu của 1 meeting — mỗi row = 1 tài liệu.
 *
 * Columns: STT, Tên tài liệu, Lượt xem, Người xem.
 *
 * "Người xem" join list tên user unique đã xem tài liệu (theo MeetingView log,
 * kind IN document_view/document_download/document_meta_view). Guest views gộp
 * thành "Khách (xN)" ở cuối list nếu N>1, hoặc "Khách" nếu N=1.
 */
class MeetingDocumentExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private int $meetingId) {}

    public function collection()
    {
        $documents = MeetingDocument::query()
            ->where('meeting_id', $this->meetingId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($documents->isEmpty()) {
            return collect();
        }

        // Pre-fetch tất cả view log của các doc này (1 query thay vì N).
        $docIds = $documents->pluck('id')->all();
        $viewsByDoc = MeetingView::query()
            ->with('user:id,name')
            ->whereIn('meeting_document_id', $docIds)
            ->get()
            ->groupBy('meeting_document_id');

        return $documents->values()->map(function ($doc, $i) use ($viewsByDoc) {
            $views = $viewsByDoc->get($doc->id, collect());

            return [
                'stt' => $i + 1,
                'title' => $doc->title,
                'view_count' => $views->count(),
                'viewers' => $this->formatViewers($views),
            ];
        });
    }

    public function headings(): array
    {
        return ['STT', 'Tên tài liệu', 'Lượt xem', 'Người xem'];
    }

    /**
     * Format: "Nguyễn Văn A, Trần Thị B, Khách (x3)" hoặc "Khách" nếu chỉ có guest.
     * User unique theo user_id; guest gộp count.
     */
    private function formatViewers($views): string
    {
        $userNames = [];
        $guestCount = 0;
        foreach ($views as $v) {
            if ($v->user_id && $v->user) {
                $userNames[$v->user_id] = $v->user->name;
            } else {
                $guestCount++;
            }
        }
        $parts = array_values($userNames);
        if ($guestCount === 1) {
            $parts[] = 'Khách';
        } elseif ($guestCount > 1) {
            $parts[] = "Khách (x{$guestCount})";
        }

        return implode(', ', $parts);
    }
}
