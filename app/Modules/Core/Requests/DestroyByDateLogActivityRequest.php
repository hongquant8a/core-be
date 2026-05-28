<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyByDateLogActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ];
    }

    public function messages(): array
    {
        return [
            'from_date.required' => 'Từ ngày không được để trống.',
            'to_date.required' => 'Đến ngày không được để trống.',
            'to_date.after_or_equal' => 'Đến ngày phải sau hoặc bằng từ ngày.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'from_date' => 'Từ ngày',
            'to_date' => 'Đến ngày',
        ];
    }
}
