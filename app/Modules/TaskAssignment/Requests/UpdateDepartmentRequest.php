<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['sometimes', StatusEnum::rule()],
            'sort_order' => 'nullable|integer|min:0',
            'is_petition_overview' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Tên phải là chuỗi ký tự.',
            'name.max' => 'Tên không được vượt quá 255 ký tự.',
            'description.string' => 'Mô tả phải là chuỗi ký tự.',
            'description.max' => 'Mô tả không được vượt quá 65535 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'sort_order.integer' => 'Thứ tự sắp xếp phải là số nguyên.',
            'sort_order.min' => 'Thứ tự sắp xếp không được nhỏ hơn 0.',
            'is_petition_overview.boolean' => 'Tổng hợp đơn thư phải là giá trị đúng/sai.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Tên phòng ban.',
                'example' => 'Phòng Kế toán',
            ],
            'description' => [
                'description' => 'Mô tả phòng ban.',
                'example' => 'Phòng quản lý tài chính kế toán.',
            ],
            'status' => [
                'description' => 'Trạng thái phòng ban.',
                'example' => StatusEnum::Active->value,
            ],
            'sort_order' => [
                'description' => 'Thứ tự sắp xếp.',
                'example' => 1,
            ],
            'is_petition_overview' => [
                'description' => 'Phòng ban tổng hợp đơn thư, được xem toàn bộ đơn thư.',
                'example' => false,
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên',
            'description' => 'Mô tả',
            'status' => 'Trạng thái',
            'sort_order' => 'Thứ tự sắp xếp',
            'is_petition_overview' => 'Tổng hợp đơn thư',
        ];
    }
}
