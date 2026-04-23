<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;

class ChangeStatusItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'processing_status' => ['required', TaskProgressStatusEnum::selectableRule()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'processing_status' => ['description' => 'Trạng thái xử lý mới. KHÔNG chấp nhận `done` — giá trị `done` được tự động set khi báo cáo cuối được xác nhận và khóa.', 'example' => TaskProgressStatusEnum::InProgress->value],
        ];
    }
}
