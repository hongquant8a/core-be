<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;

class StoreLookupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['required', StatusEnum::rule()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Tên danh mục.',
                'example' => 'Loại giao việc nội bộ',
            ],
            'description' => [
                'description' => 'Mô tả danh mục.',
                'example' => 'Danh mục dùng cho bộ lọc giao việc.',
            ],
            'status' => [
                'description' => 'Trạng thái danh mục.',
                'example' => StatusEnum::Active->value,
            ],
        ];
    }
}
