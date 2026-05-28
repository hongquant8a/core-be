<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;

class UpdateItemProgressRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'processing_status' => ['sometimes', TaskProgressStatusEnum::selectableRule()],
            'completion_percent' => 'sometimes|integer|min:0|max:100',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'processing_status' => [
                'description' => 'Trạng thái xử lý công việc.',
                'example' => TaskProgressStatusEnum::InProgress->value,
            ],
            'completion_percent' => [
                'description' => 'Phần trăm hoàn thành công việc (0-100).',
                'example' => 75,
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'processing_status' => 'Trạng thái xử lý',
            'completion_percent' => 'Phần trăm hoàn thành',
        ];
    }
}
