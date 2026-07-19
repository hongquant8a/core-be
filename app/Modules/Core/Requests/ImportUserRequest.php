<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportUserRequest extends FormRequest
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
            'file.required' => 'Vui lòng chọn file để nhập.',
            'file.file' => 'File nhập không hợp lệ.',
            'file.mimes' => 'File phải có định dạng: xlsx, xls, csv.',
            'file.max' => 'File không được vượt quá dung lượng cho phép.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'file' => 'Tệp tin',
        ];
    }
}
