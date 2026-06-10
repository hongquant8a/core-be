<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;

class StoreDepartmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['required', StatusEnum::rule()],
            'sort_order' => 'nullable|integer|min:0',
            'is_petition_overview' => 'boolean',
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
