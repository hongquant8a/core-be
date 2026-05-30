<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class ChangeStatusSchedulingEmployeeRequest extends FormRequest
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
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
