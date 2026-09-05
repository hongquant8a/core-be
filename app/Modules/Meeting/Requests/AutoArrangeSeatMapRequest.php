<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AutoArrangeSeatMapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:rank,abc,random'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'in' => ':attribute không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'mode' => 'Kiểu xếp',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'mode' => ['description' => 'Kiểu tự động xếp: rank (chức vụ) | abc (tên A-Z) | random (ngẫu nhiên).', 'example' => 'rank'],
        ];
    }
}
