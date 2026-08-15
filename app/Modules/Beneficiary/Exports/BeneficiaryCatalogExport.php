<?php

namespace App\Modules\Beneficiary\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Xuất một trong ba bảng danh mục — cùng tập cột nên dùng chung một lớp, chỉ khác nhãn để
 * đặt tên sheet.
 */
class BeneficiaryCatalogExport extends AbstractExcelExport implements FromCollection, WithMapping, WithTitle
{
    public function __construct(
        private readonly Collection $items,
        private readonly string $label,
    ) {}

    public function collection(): Collection
    {
        return $this->items;
    }

    public function title(): string
    {
        return mb_convert_case($this->label, MB_CASE_TITLE, 'UTF-8');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tên',
            'Ghi chú',
            'Thứ tự',
            'Trạng thái',
            'Số bản ghi đang dùng',
            'Người tạo',
            'Người cập nhật',
            'Ngày tạo',
            'Ngày cập nhật',
        ];
    }

    /** @param  Model  $item */
    public function map($item): array
    {
        // Tổ dân phố bị tham chiếu từ hai bảng nên cộng cả hai; hai danh mục còn lại chỉ có
        // một quan hệ và key kia không tồn tại → null coalesce về 0.
        $usage = (int) ($item->beneficiaries_count ?? 0)
            + (int) ($item->dependents_count ?? 0)
            + (int) ($item->type_relations_count ?? 0);

        return [
            $item->id,
            $item->name,
            $item->note,
            $item->sort_order,
            $item->status?->label(),
            $usage,
            $item->creator?->name,
            $item->editor?->name,
            $item->created_at?->format('H:i:s d/m/Y'),
            $item->updated_at?->format('H:i:s d/m/Y'),
        ];
    }
}
