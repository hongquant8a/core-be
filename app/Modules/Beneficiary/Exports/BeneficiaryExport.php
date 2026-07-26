<?php

namespace App\Modules\Beneficiary\Exports;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Core\Exports\AbstractExcelExport;
use Maatwebsite\Excel\Concerns\FromCollection;

class BeneficiaryExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(private array $filters = []) {}

    public function collection()
    {
        return Beneficiary::with(['household', 'residentialArea', 'classifications', 'dependents', 'primaryDependentRelation.dependent', 'documents', 'creator', 'editor'])
            ->filter($this->filters)
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn ($b, $i) => [
                'stt' => $i + 1,
                'full_name' => $b->full_name,
                'date_of_birth' => $b->date_of_birth?->format('d/m/Y'),
                'birth_year' => $b->birth_year,
                'gender' => GenderEnum::tryFrom((string) $b->gender)?->label() ?? $b->gender,
                'id_number' => $b->id_number,
                'head_id_number' => $b->household?->head_id_number,
                'residential_area' => $b->residentialArea?->name,
                'status' => BeneficiaryStatusEnum::tryFrom((string) $b->status)?->label() ?? $b->status,
                'death_date' => $b->death_date?->format('d/m/Y'),
                'address' => $b->address,
                'latitude' => $b->latitude,
                'longitude' => $b->longitude,
                'phone' => $b->phone,
                'note' => $b->note,
                // Quan hệ 1-N / N-N — liệt kê ngăn cách bởi "; " (chỉ tham chiếu, import bỏ qua).
                'classifications' => $b->classifications
                    ->map(fn ($c) => BeneficiaryTypeEnum::tryFrom((string) $c->type)?->label() ?? $c->type)
                    ->implode('; '),
                // Quan hệ 1-1 → xuất TÊN (đầu mối liên hệ khi người có công đã mất).
                'primary_dependent' => $b->primaryDependentRelation?->dependent?->full_name,
                // N-N có thuộc tính pivot → kèm nhãn quan hệ trong ngoặc (đối xứng với DependentExport).
                'dependents' => $b->dependents
                    ->map(fn ($d) => $d->full_name.' ('.(DependentRelationshipEnum::tryFrom((string) $d->pivot->relationship_type)?->label() ?? $d->pivot->relationship_type).')')
                    ->implode('; '),
                'documents' => $b->documents->pluck('name')->implode('; '),
                'created_by' => $b->creator?->name ?? 'N/A',
                'updated_by' => $b->editor?->name ?? 'N/A',
                'created_at' => $b->created_at?->format('H:i:s d/m/Y'),
                'updated_at' => $b->updated_at?->format('H:i:s d/m/Y'),
                'id' => $b->id,
            ]);
    }

    public function headings(): array
    {
        return ['STT', 'Họ tên', 'Ngày sinh', 'Năm sinh', 'Giới tính', 'CCCD/CMND', 'CCCD chủ hộ', 'Tổ dân phố', 'Trạng thái', 'Ngày mất', 'Địa chỉ', 'Vĩ độ', 'Kinh độ', 'SĐT', 'Ghi chú', 'Thân nhân chính', 'Loại đối tượng', 'Thân nhân', 'Giấy tờ', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
