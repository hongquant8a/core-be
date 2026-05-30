<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class ChangeStatusSchedulingEmployeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', StatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'trạng thái',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Trạng thái mới của nhóm nhân viên lịch công tác.',
                'example' => StatusEnum::Active->value,
            ],
        ];
    }
}
