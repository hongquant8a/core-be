<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;
use App\Modules\TaskAssignment\Exports\Concerns\StripsHtml;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use Maatwebsite\Excel\Concerns\FromCollection;

class DocumentsExport extends AbstractExcelExport implements FromCollection
{
    use StripsHtml;

    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return TaskAssignmentDocument::with(['type', 'creator', 'editor'])
            ->withCount('items')
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($doc, $i) => [
                'stt' => $i + 1,
                'name' => $doc->name,
                'summary' => $this->stripHtml($doc->summary),
                'issue_date' => $doc->issue_date?->format('d/m/Y'),
                'type' => $doc->type?->name ?? 'N/A',
                'status' => TaskAssignmentDocumentStatusEnum::tryFrom((string) $doc->status)?->label() ?? $doc->status,
                'issued_at' => $doc->issued_at?->format('H:i:s d/m/Y'),
                'items_count' => $doc->items_count,
                'created_by' => $doc->creator?->name ?? 'N/A',
                'updated_by' => $doc->editor?->name ?? 'N/A',
                'created_at' => $doc->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $doc->updated_at?->format('H:i:s d/m/Y'),
                'id' => $doc->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên văn bản', 'Tóm tắt', 'Ngày ban hành', 'Loại văn bản', 'Trạng thái', 'Thời điểm ban hành', 'Số công việc', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
