<?php

namespace App\Modules\Beneficiary\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sắp xếp lại thứ tự hiển thị của danh mục trong dropdown.
 */
class ReorderCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Vui lòng gửi danh sách cần sắp xếp.',
            'items.array' => 'Danh sách sắp xếp phải là mảng.',
            'items.min' => 'Vui lòng gửi ít nhất một mục.',
            'items.max' => 'Chỉ được sắp xếp tối đa :max mục mỗi lần.',
            'items.*.id.required' => 'ID không được để trống.',
            'items.*.id.integer' => 'ID phải là số nguyên.',
            'items.*.sort_order.required' => 'Thứ tự không được để trống.',
            'items.*.sort_order.integer' => 'Thứ tự phải là số nguyên.',
            'items.*.sort_order.min' => 'Thứ tự không được nhỏ hơn :min.',
            'items.*.sort_order.max' => 'Thứ tự không được lớn hơn :max.',
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => 'danh sách sắp xếp',
            'items.*.id' => 'ID',
            'items.*.sort_order' => 'thứ tự',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'items' => [
                'description' => 'Danh sách cặp id + thứ tự mới.',
                'example' => [['id' => 3, 'sort_order' => 1], ['id' => 1, 'sort_order' => 2]],
            ],
        ];
    }
}
