<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Services\OrganizationService;
use Maatwebsite\Excel\Concerns\FromCollection;

class OrganizationsExport extends AbstractExcelExport implements FromCollection
{
    public function __construct(
        protected array $filters = []
    ) {}

    public function collection()
    {
        $service = app(OrganizationService::class);
        // Giữ tree order (parent-trước-children) — không sort theo ID
        // để preserve cấu trúc phân cấp tổ chức cho người xem.
        $items = $service->getFlatTreeOrdered($this->filters);

        return $items->values()->map(fn ($o, $i) => [
            'stt' => $i + 1,
            'name' => $o->name,
            'slug' => $o->slug,
            'description' => $o->description,
            'status' => StatusEnum::tryFrom((string) $o->status)?->label() ?? $o->status,
            'parent_slug' => $o->parent_id ? (Organization::find($o->parent_id)?->slug ?? '') : '',
            'sort_order' => $o->sort_order,
            'depth' => $service->getDepth($o),
            'created_by' => $o->creator?->name ?? 'N/A',
            'updated_by' => $o->editor?->name ?? 'N/A',
            'created_at' => $o->created_at?->format('H:i:s d/m/Y'),
            'updated_at' => $o->updated_at?->format('H:i:s d/m/Y'),
            'id' => $o->id,
        ]);
    }

    public function headings(): array
    {
        return ['STT', 'Tên tổ chức', 'Mã định danh', 'Mô tả', 'Trạng thái', 'Mã định danh tổ chức cha', 'Thứ tự', 'Cấp', 'Người tạo', 'Người cập nhật', 'Ngày tạo', 'Ngày cập nhật', 'ID'];
    }
}
