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

    public function messages(): array
    {
        return [
            'name.string' => 'Tên phải là chuỗi ký tự.',
            'name.max' => 'Tên không được vượt quá 255 ký tự.',
            'description.string' => 'Mô tả phải là chuỗi ký tự.',
            'description.max' => 'Mô tả không được vượt quá 65535 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ.',
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

    public function attributes(): array
    {
        return [
            'name' => 'Tên',
            'description' => 'Mô tả',
            'status' => 'Trạng thái',
        ];
    }
}
