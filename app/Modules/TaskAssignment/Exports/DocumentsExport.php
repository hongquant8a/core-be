<?php

namespace App\Modules\TaskAssignment\Exports;

use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DocumentsExport implements FromCollection, WithHeadings
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return TaskAssignmentDocument::with(['type', 'creator', 'editor'])
            ->withCount('items')
            ->filter($this->filters)
            ->get()
            ->map(fn ($doc) => [
                'id' => $doc->id,
                'name' => $doc->name,
                'summary' => $doc->summary,
                'issue_date' => $doc->issue_date?->format('d/m/Y'),
                'type' => $doc->type?->name ?? 'N/A',
                'status' => $doc->status,
                'issued_at' => $doc->issued_at?->format('H:i:s d/m/Y'),
                'items_count' => $doc->items_count,
                'created_by' => $doc->creator?->name ?? 'N/A',
                'updated_by' => $doc->editor?->name ?? 'N/A',
                'created_at' => $doc->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $doc->updated_at?->format('H:i:s d/m/Y'),
            ]);
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Summary', 'Issue Date', 'Type', 'Status', 'Issued At', 'Items Count', 'Created By', 'Updated By', 'Created At', 'Updated At'];
    }
}
