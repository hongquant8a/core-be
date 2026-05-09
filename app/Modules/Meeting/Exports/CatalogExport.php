<?php

namespace App\Modules\Meeting\Exports;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export chung cho 4 catalog: MeetingType / MeetingLocation / MeetingDocumentType / MeetingAttendeeGroup.
 * Cột "Địa chỉ" + "Google Maps URL" chỉ render với MeetingLocation; 3 catalog còn lại bỏ luôn 2 cột này.
 */
class CatalogExport implements FromCollection, WithHeadings
{
    private bool $hasLocation;

    public function __construct(
        private string $modelClass,
        private array $filters = [],
    ) {
        /** @var Model $model */
        $model = app($this->modelClass);
        $this->hasLocation = in_array('address', $model->getFillable(), true);
    }

    public function collection()
    {
        /** @var Model $model */
        $model = app($this->modelClass);

        return $model->newQuery()
            ->with(['creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($item, $i) => array_filter([
                'stt' => $i + 1,
                'name' => $item->name,
                'description' => $item->description,
                'address' => $this->hasLocation ? ($item->address ?? '') : null,
                'google_maps_url' => $this->hasLocation ? ($item->google_maps_url ?? '') : null,
                'status' => MeetingCatalogStatusEnum::tryFrom((string) $item->status)?->label() ?? $item->status,
                'created_by' => $item->creator?->name ?? 'N/A',
                'updated_by' => $item->editor?->name ?? 'N/A',
                'created_at' => $item->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $item->updated_at?->format('H:i:s d/m/Y'),
                'id' => $item->id,
            ], fn ($v) => $v !== null));
    }

    public function headings(): array
    {
        $head = ['STT', 'Tên', 'Mô tả'];
        if ($this->hasLocation) {
            $head[] = 'Địa chỉ';
            $head[] = 'Google Maps URL';
        }
        $head = array_merge($head, ['Trạng thái', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID']);

        return $head;
    }
}
