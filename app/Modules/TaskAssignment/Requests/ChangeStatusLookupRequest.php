<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;

class ChangeStatusLookupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', StatusEnum::rule()],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Trạng thái mới của danh mục.',
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
