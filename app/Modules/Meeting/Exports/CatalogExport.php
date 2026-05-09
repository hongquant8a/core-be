<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export chung cho 4 catalog: MeetingType / MeetingLocation / MeetingDocumentType / MeetingAttendeeGroup.
 * Cột địa lý chỉ có giá trị thực với MeetingLocation; với 3 catalog còn lại cột để trống.
 */
class CatalogExport implements FromCollection, WithHeadings
{
    public function __construct(
        private string $modelClass,
        private array $filters = [],
    ) {}

    public function collection()
    {
        /** @var Model $model */
        $model = app($this->modelClass);
        $hasLocation = $this->modelHasLocationFields($model);

        return $model->newQuery()
            ->with(['creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($item, $i) => [
                'stt' => $i + 1,
                'name' => $item->name,
                'description' => $item->description,
                'address' => $hasLocation ? $item->address : null,
                'google_maps_url' => $hasLocation ? $item->google_maps_url : null,
                'status' => MeetingCatalogStatusEnum::tryFrom((string) $item->status)?->label() ?? $item->status,
                'created_by' => $item->creator?->name ?? 'N/A',
                'updated_by' => $item->editor?->name ?? 'N/A',
                'created_at' => $item->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $item->updated_at?->format('H:i:s d/m/Y'),
                'id' => $item->id,
            ]);
    }

    public function headings(): array
    {
        return [
            'STT', 'Tên', 'Mô tả',
            'Địa chỉ', 'Google Maps URL',
            'Trạng thái',
            'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID',
        ];
    }

    private function modelHasLocationFields(Model $model): bool
    {
        return in_array('address', $model->getFillable(), true);
    }
}
