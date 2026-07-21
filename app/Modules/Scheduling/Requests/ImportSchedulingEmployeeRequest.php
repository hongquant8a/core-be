<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportSchedulingEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Tệp không được để trống.',
            'file.file' => 'Tệp tải lên không hợp lệ.',
            'file.mimes' => 'Tệp phải có định dạng xlsx, xls hoặc csv.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'file' => 'Tệp',
        ];
    }
}
