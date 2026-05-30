<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dates' => 'required|array|min:1',
            'dates.*' => 'required|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'array' => ':attribute phải là mảng.',
            'date_format' => ':attribute không đúng định dạng Y-m-d.',
            'min' => 'Phải chọn ít nhất :min ngày để sao chép.',
        ];
    }

    public function attributes(): array
    {
        return [
            'dates' => 'Danh sách ngày sao chép',
            'dates.*' => 'Ngày sao chép',
        ];
    }
}
