<?php

namespace App\Modules\Beneficiary\Exports;

use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Core\Exports\AbstractExcelExport;
use Maatwebsite\Excel\Concerns\FromCollection;

class DependentExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return Dependent::with(['household', 'residentialArea', 'beneficiaries', 'creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($d, $i) => [
                'stt' => $i + 1,
                'full_name' => $d->full_name,
                'date_of_birth' => $d->date_of_birth?->format('d/m/Y'),
                'gender' => GenderEnum::tryFrom((string) $d->gender)?->label() ?? $d->gender,
                'id_number' => $d->id_number,
                'head_id_number' => $d->household?->head_id_number,
                'residential_area' => $d->residentialArea?->name,
                'phone' => $d->phone,
                'latitude' => $d->latitude,
                'longitude' => $d->longitude,
                'note' => $d->note,
                // Quan hệ N-N với người có công — "Tên (Quan hệ)" ngăn cách bởi "; " (chỉ tham chiếu).
                'beneficiaries' => $d->beneficiaries
                    ->map(fn ($b) => $b->full_name.' ('.(DependentRelationshipEnum::tryFrom((string) $b->pivot->relationship_type)?->label() ?? $b->pivot->relationship_type).')')
                    ->implode('; '),
                'created_by' => $d->creator?->name ?? 'N/A',
                'updated_by' => $d->editor?->name ?? 'N/A',
                'created_at' => $d->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $d->updated_at?->format('H:i:s d/m/Y'),
                'id' => $d->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Họ tên', 'Ngày sinh', 'Giới tính', 'CCCD/CMND', 'CCCD chủ hộ', 'Tổ dân phố', 'SĐT', 'Vĩ độ', 'Kinh độ', 'Ghi chú', 'Người có công liên kết', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
