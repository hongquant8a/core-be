<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Meeting\Models\MeetingDocument;
use Maatwebsite\Excel\Concerns\FromCollection;

/**
 * Xuất danh sách tài liệu của 1 meeting — mỗi row = 1 tài liệu.
 *
 * Columns: STT, Tên tài liệu, Loại tài liệu, Thuộc chương trình, Tổng lượt xem, Người xem.
 *
 * $isPrivileged=false (guest hoặc auth-không-vai-trò) chỉ xuất doc is_public=true.
 * $isPrivileged=true (chair/op/participant) xuất đầy đủ.
 *
 * Stats lượt xem / người xem được tách sang endpoint chi tiết riêng
 * (MeetingDocumentViewExport).
 */
class MeetingDocumentExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private int $meetingId, private bool $isPrivileged = false) {}

    public function collection()
    {
        return MeetingDocument::query()
            ->with(['documentType:id,name', 'agenda:id,content', 'views.user:id,name'])
            ->where('meeting_id', $this->meetingId)
            ->when(! $this->isPrivileged, fn ($q) => $q->where('is_public', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn ($doc, $i) => [
                'stt' => $i + 1,
                'title' => $doc->title,
                'document_type' => $doc->documentType?->name ?? '',
                'agenda' => $doc->agenda?->content ?? '',
                'view_count' => $doc->view_count,
                'viewers' => $doc->views->map(fn ($v) => $v->user ? $v->user->name : 'Khách')->unique()->implode(', '),
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên tài liệu', 'Loại tài liệu', 'Thuộc chương trình', 'Tổng lượt xem', 'Người xem'];
    }
}
