<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;

class UpdateEmployeeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', StatusEnum::rule()],
            'note' => 'sometimes|nullable|string|max:65535',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Trạng thái không hợp lệ.',
            'note.max' => 'Ghi chú quá dài.',
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'trạng thái',
            'note' => 'ghi chú',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Trạng thái nhân viên.',
                'example' => StatusEnum::Active->value,
            ],
            'note' => [
                'description' => 'Ghi chú nội bộ.',
                'example' => 'Tạm ngưng do nghỉ phép.',
            ],
        ];
    }
}
