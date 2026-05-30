<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchedulingEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', StatusEnum::rule()],
            'note' => 'sometimes|nullable|string|max:65535',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Trạng thái không hợp lệ.',
            'note.max' => 'Ghi chú quá dài.',
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => 'trạng thái',
            'note' => 'ghi chú',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'Trạng thái nhân viên.',
                'example' => StatusEnum::Active->value,
            ],
            'note' => [
                'description' => 'Ghi chú nội bộ.',
                'example' => 'Tạm ngưng do nghỉ phép.',
            ],
        ];
    }
}
