<?php

namespace App\Modules\Beneficiary\Exports;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Core\Exports\AbstractExcelExport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Xuất danh sách người có công.
 *
 * Ba cột "Danh sách ..." gộp quan hệ 1–n vào MỘT ô, ngăn bởi `'; '` (CLAUDE.md B7). Chúng chỉ
 * mang tính tham chiếu để đọc — **import bỏ qua**, và tên header cố ý khác cột nhập liệu để
 * cán bộ không nhầm là có thể nhập ngược.
 *
 * Không có cột "Trạng thái": bảng chính không có cột này.
 */
class BeneficiaryExport extends AbstractExcelExport implements FromCollection, WithMapping
{
    public function __construct(private readonly Collection $items) {}

    public function collection(): Collection
    {
        return $this->items;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Họ và tên',
            'Ngày sinh',
            'Năm sinh',
            'Giới tính',
            'CCCD/CMND',
            'Số điện thoại',
            'Tổ dân phố/Thôn',
            'Địa chỉ',
            'Vĩ độ',
            'Kinh độ',
            'Ghi chú',
            'Danh sách loại đối tượng',
            'Danh sách thân nhân',
            'Danh sách tài liệu',
            'Người tạo',
            'Người cập nhật',
            'Ngày tạo',
            'Ngày cập nhật',
        ];
    }

    /** @param  Beneficiary  $item */
    public function map($item): array
    {
        return [
            $item->id,
            $item->full_name,
            $item->birth_date?->format('d/m/Y'),
            $item->birth_year,
            $item->gender?->label(),
            $item->id_number,
            $item->phone,
            $item->residentialArea?->name,
            $item->address,
            $item->latitude,
            $item->longitude,
            $item->note,

            // Quan hệ n–n: chỉ tên loại đối tượng, đánh dấu (chính) cho dòng is_primary.
            $item->typeRelations
                ->map(fn ($r) => $r->beneficiaryType?->name.($r->is_primary ? ' (chính)' : ''))
                ->filter()
                ->implode('; '),

            // Quan hệ 1–n có thuộc tính pivot → kèm nhãn trong ngoặc (CLAUDE.md B7).
            $item->dependents
                ->map(fn ($d) => $d->full_name
                    .($d->relationship?->name ? ' ('.$d->relationship->name.')' : '')
                    .($d->is_primary ? ' (chính)' : ''))
                ->filter()
                ->implode('; '),

            $item->documents->pluck('name')->filter()->implode('; '),

            $item->creator?->name,
            $item->editor?->name,
            $item->created_at?->format('H:i:s d/m/Y'),
            $item->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
