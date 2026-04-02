<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;

class BulkUpdateStatusDepartmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'status' => ['required', StatusEnum::rule()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Danh sách ID phòng ban cần cập nhật trạng thái.',
                'example' => [1, 2, 3],
            ],
            'status' => [
                'description' => 'Trạng thái mới áp dụng hàng loạt.',
                'example' => StatusEnum::Inactive->value,
            ],
        ];
    }
}
