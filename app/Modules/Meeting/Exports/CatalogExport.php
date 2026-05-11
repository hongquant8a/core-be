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
            ->map(function ($item, $i) {
                // Build row conditionally — KHÔNG dùng array_filter null vì sẽ shift cell (mất alignment với headings).
                // Null field giữ nguyên empty string '' để Excel render đúng cột.
                $row = [
                    'stt' => $i + 1,
                    'name' => $item->name ?? '',
                    'description' => $item->description ?? '',
                ];
                if ($this->hasLocation) {
                    $row['address'] = $item->address ?? '';
                    $row['google_maps_url'] = $item->google_maps_url ?? '';
                }
                $row['status'] = MeetingCatalogStatusEnum::tryFrom((string) $item->status)?->label() ?? ($item->status ?? '');
                $row['created_by'] = $item->creator?->name ?? 'N/A';
                $row['updated_by'] = $item->editor?->name ?? 'N/A';
                $row['created_at'] = $item->created_at?->format('H:i:s d/m/Y') ?? '';
                $row['updated_at'] = $item->updated_at?->format('H:i:s d/m/Y') ?? '';
                $row['id'] = $item->id;

                return $row;
            });
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
