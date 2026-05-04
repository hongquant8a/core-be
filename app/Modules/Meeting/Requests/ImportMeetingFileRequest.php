<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportMeetingFileRequest extends FormRequest
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
            'file.required' => 'Tệp là trường bắt buộc.',
            'file.file' => 'Tệp phải là file hợp lệ.',
            'file.mimes' => 'Tệp phải đúng định dạng cho phép (xlsx, xls, csv).',
            'file.max' => 'Tệp không được vượt quá :max KB.',
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'Tệp'];
    }

    public function bodyParameters(): array
    {
        return [
            'file' => [
                'description' => 'File Excel danh mục (xlsx, xls, csv).',
                'example' => 'danh-muc.xlsx',
            ],
        ];
    }
}
