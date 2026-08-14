<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\PetitionStatusEnum;

class BulkUpdateStatusPetitionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:task_assignment_petitions,id',
            'processing_status' => ['required', PetitionStatusEnum::rule()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Danh sách ID đơn thư.', 'example' => [1, 2, 3]],
            'processing_status' => ['description' => 'Trạng thái xử lý mới.', 'example' => PetitionStatusEnum::Processing->value],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Danh sách ID',
            'ids.*' => 'ID',
            'processing_status' => 'Trạng thái xử lý',
        ];
    }
}
