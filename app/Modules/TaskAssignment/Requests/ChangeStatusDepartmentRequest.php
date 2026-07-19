<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;

class ChangeStatusDepartmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', StatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái không được để trống.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Trạng thái mới của phòng ban.',
                'example' => StatusEnum::Active->value,
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'Trạng thái',
        ];
    }
}
