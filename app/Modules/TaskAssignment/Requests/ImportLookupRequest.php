<?php

namespace App\Modules\TaskAssignment\Requests;

class ImportLookupRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv',
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
}
