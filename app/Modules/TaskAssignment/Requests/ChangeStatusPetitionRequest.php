<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\PetitionStatusEnum;

class ChangeStatusPetitionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'processing_status' => ['required', PetitionStatusEnum::rule()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'processing_status' => ['description' => 'Trạng thái xử lý mới.', 'example' => PetitionStatusEnum::Processing->value],
        ];
    }

    public function attributes(): array
    {
        return [
            'processing_status' => 'Trạng thái xử lý',
        ];
    }
}
