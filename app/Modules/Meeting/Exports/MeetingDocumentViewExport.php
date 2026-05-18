<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Meeting\Models\MeetingView;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Xuất chi tiết lượt xem tài liệu của 1 meeting — mỗi row = 1 lượt xem.
 *
 * Columns: STT, Tên tài liệu, Người xem, Loại, Thời gian xem, IP.
 *
 * "Người xem" hiển thị name của user; nếu user_id null (guest) thì hiển thị "Khách".
 * "Loại" map kind từ DB: document_meta_view → "Mở trang tài liệu",
 * document_view → "Xem nội dung", document_download → "Tải xuống".
 */
class MeetingDocumentViewExport extends AbstractExcelExport implements FromCollection
{
    private const KIND_LABELS = [
        'document_meta_view' => 'Mở trang tài liệu',
        'document_view' => 'Xem nội dung',
        'document_download' => 'Tải xuống',
    ];

    public function __construct(private int $meetingId) {}

    public function collection()
    {
        return MeetingView::query()
            ->with(['user:id,name', 'meetingDocument:id,title'])
            ->where('meeting_id', $this->meetingId)
            ->whereNotNull('meeting_document_id')
            ->orderByDesc('viewed_at')
            ->get()
            ->values()
            ->map(fn ($v, $i) => [
                'stt' => $i + 1,
                'document' => $v->meetingDocument?->title ?? '',
                'viewer' => $v->user_id && $v->user ? $v->user->name : 'Khách',
                'kind' => self::KIND_LABELS[$v->kind] ?? $v->kind,
                'viewed_at' => $v->viewed_at?->format('H:i:s d/m/Y'),
                'ip' => (string) ($v->ip_address ?? ''),
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên tài liệu', 'Người xem', 'Loại', 'Thời gian xem', 'IP'];
    }
}
