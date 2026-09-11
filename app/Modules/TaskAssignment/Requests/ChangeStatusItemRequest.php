<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;

class ChangeStatusItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Chỉ còn hai giá trị: tạm dừng và huỷ đã có endpoint riêng
            // (`/pause`, `/cancel`) với quyền riêng, nhận chúng ở đây là mở lại
            // đúng cái cửa vừa đóng.
            'processing_status' => ['required', 'in:'.TaskProgressStatusEnum::Todo->value.','.TaskProgressStatusEnum::InProgress->value],
        ];
    }

    public function messages(): array
    {
        return [
            'processing_status.required' => 'Phải chọn trạng thái xử lý.',
            'processing_status.in' => 'Chỉ đổi được sang Chưa thực hiện hoặc Đang thực hiện. Tạm dừng, huỷ và hoàn thành có thao tác riêng.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'processing_status' => ['description' => 'Trạng thái xử lý mới. Chỉ nhận `todo` hoặc `in_progress`. Tạm dừng dùng `/pause`, huỷ dùng `/cancel`, hoàn thành do `/mark-done` tự set.', 'example' => TaskProgressStatusEnum::InProgress->value],
        ];
    }

    public function attributes(): array
    {
        return [
            'processing_status' => 'Trạng thái xử lý',
        ];
    }
}
