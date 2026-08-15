<?php

namespace App\Modules\Beneficiary\Requests\Catalog;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateCatalogStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'min:1'],
            'status' => ['required', CatalogStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Vui lòng chọn ít nhất một bản ghi.',
            'ids.array' => 'Danh sách ID phải là mảng.',
            'ids.min' => 'Vui lòng chọn ít nhất một bản ghi.',
            'ids.max' => 'Chỉ được cập nhật tối đa :max bản ghi mỗi lần.',
            'ids.*.required' => 'ID không được để trống.',
            'ids.*.integer' => 'ID phải là số nguyên.',
            'ids.*.min' => 'ID không hợp lệ.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'danh sách ID', 'ids.*' => 'ID', 'status' => 'trạng thái'];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Danh sách ID cần đổi trạng thái.', 'example' => [1, 2, 3]],
            'status' => ['description' => 'active hoặc inactive.', 'example' => 'inactive'],
        ];
    }
}
