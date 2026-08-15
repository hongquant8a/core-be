<?php

namespace App\Modules\Beneficiary\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dùng chung cho mọi endpoint import của module (bản chính và ba danh mục).
 */
class ImportBeneficiaryFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn tệp cần nhập.',
            'file.file' => 'Tệp nhập không hợp lệ.',
            'file.mimes' => 'Tệp nhập phải thuộc định dạng: xlsx, xls, csv.',
            'file.max' => 'Tệp nhập không được vượt quá 10MB.',
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'tệp nhập'];
    }

    public function bodyParameters(): array
    {
        return [
            'file' => ['description' => 'Tệp Excel/CSV cần nhập. Tải file mẫu ở endpoint /import-template.'],
        ];
    }
}
