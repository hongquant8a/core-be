<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là bắt buộc.',
            'file' => ':attribute phải là một tệp tin.',
            'mimes' => ':attribute phải ở định dạng xlsx, xls hoặc csv.',
            'max' => ':attribute dung lượng tối đa là 10MB.',
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'Tệp tin Excel',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'file' => [
                'description' => 'Tệp tin Excel chứa dữ liệu lịch công tác cần nhập.',
                'example' => 'import_template.xlsx',
            ],
        ];
    }
}
