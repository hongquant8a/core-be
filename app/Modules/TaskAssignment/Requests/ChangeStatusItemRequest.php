<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;

class ChangeStatusItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'processing_status' => ['required', TaskProgressStatusEnum::rule()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'processing_status' => ['description' => 'Trạng thái xử lý mới.', 'example' => TaskProgressStatusEnum::Done->value],
        ];
    }
}
