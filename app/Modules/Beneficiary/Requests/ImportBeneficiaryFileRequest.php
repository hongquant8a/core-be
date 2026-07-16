<?php

namespace App\Modules\Beneficiary\Requests;

/**
 * Request import file dùng chung cho mọi resource của module (Household, Beneficiary, Dependent, SubsidyPolicy)
 * — chỉ validate định dạng file, cột dữ liệu tự validate riêng trong từng Import class.
 */
class ImportBeneficiaryFileRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn file để import.',
            'file.file' => 'File không hợp lệ.',
            'file.mimes' => 'File phải có định dạng xlsx, xls hoặc csv.',
            'file.max' => 'File không được vượt quá 10MB.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'file' => ['description' => 'File Excel (xlsx, xls, csv).', 'example' => 'households.xlsx'],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'File'];
    }
}
