<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class ChangeStatusScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', ScheduleStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'integer' => ':attribute phải là số nguyên.',
            'in' => ':attribute không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'Trạng thái mới',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Trạng thái mới (0: Nháp, 1: Chờ duyệt, 2: Đã duyệt/Công bố, 3: Đã hủy).',
                'example' => 2,
            ],
        ];
    }
}
