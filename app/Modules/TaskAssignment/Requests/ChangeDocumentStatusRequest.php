<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;

class ChangeDocumentStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', TaskAssignmentDocumentStatusEnum::rule()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Trạng thái mới của văn bản giao việc.',
                'example' => TaskAssignmentDocumentStatusEnum::Issued->value,
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
