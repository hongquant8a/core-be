<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;

class UpdateLookupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['sometimes', StatusEnum::rule()],
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
