<?php

namespace App\Modules\TaskAssignment\Requests;

class ImportLookupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'file' => [
                'description' => 'File import danh mục (định dạng xlsx, xls, csv).',
                'example' => 'danh_muc.xlsx',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'Tệp tin',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Tệp tin không được để trống.',
            'file.file' => 'Tệp tin không hợp lệ.',
            'file.mimes' => 'Tệp tin phải có định dạng xlsx, xls hoặc csv.',
            'file.max' => 'Tệp tin không được vượt quá 10MB.',
        ];
    }
}
