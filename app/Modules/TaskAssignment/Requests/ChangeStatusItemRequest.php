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

    public function messages(): array
    {
        return [
            'processing_status.required' => 'Trạng thái xử lý không được để trống.',
            'processing_status.in' => 'Trạng thái xử lý không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'processing_status' => ['description' => 'Trạng thái xử lý mới. KHÔNG chấp nhận `done` — giá trị `done` được tự động set khi báo cáo cuối được xác nhận và khóa.', 'example' => TaskProgressStatusEnum::InProgress->value],
        ];
    }

    public function attributes(): array
    {
        return [
            'processing_status' => 'Trạng thái xử lý',
        ];
    }
}
