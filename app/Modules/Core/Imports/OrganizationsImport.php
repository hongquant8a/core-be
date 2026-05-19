<?php

namespace App\Modules\Core\Imports;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Services\OrganizationService;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class OrganizationsImport implements ToModel, WithHeadingRow, WithValidation
{
    use TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Tên tổ chức',
        'slug' => 'Mã định danh',
        'description' => 'Mô tả',
        'status' => 'Trạng thái',
        'sort_order' => 'Thứ tự',
        'parent_slug' => 'Mã định danh tổ chức cha',
    ];

    /** Subset xuất ra template — chỉ field required theo StoreOrganizationRequest. */
    public const TEMPLATE_LABELS = [
        'name' => 'Tên tổ chức',
        'status' => 'Trạng thái',
    ];

    public const TEMPLATE_EXAMPLES = [
        'name' => 'UBND Phường Hòa An (xóa hàng này)',
        'status' => 'active',
    ];

    public function model(array $row)
    {
        $parentSlug = $row['parent_slug'] ?? $row['parent slug'] ?? '';
        $parent = $parentSlug ? Organization::where('slug', $parentSlug)->first() : null;
        $name = $row['name'] ?? $row['name_'] ?? '';
        $status = $row['status'] ?? StatusEnum::Active->value;

        return new Organization([
            'name' => $name,
            'slug' => app(OrganizationService::class)->generateUniqueSlug($row['slug'] ?? Str::slug($name)),
            'description' => $row['description'] ?? null,
            'status' => in_array($status, StatusEnum::values()) ? $status : StatusEnum::Active->value,
            'parent_id' => $parent?->id,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['name'] = isset($data['name']) ? (string) $data['name'] : null;
        $data['status'] = isset($data['status']) ? (string) $data['status'] : null;

        return $data;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'status' => 'nullable|string|in:'.implode(',', StatusEnum::values()),
            'sort_order' => 'nullable|integer',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên tổ chức không được để trống.',
            'name.string' => 'Tên tổ chức phải là một chuỗi ký tự.',
            'status.in' => 'Trạng thái không hợp lệ (:input).',
            'sort_order.integer' => 'Thứ tự sắp xếp phải là số nguyên.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên tổ chức',
            'slug' => 'Mã định danh',
            'description' => 'Mô tả',
            'status' => 'Trạng thái',
            'parent_slug' => 'Mã định danh tổ chức cha',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
}
