<?php

namespace App\Modules\TaskAssignment\Requests;

class ImportItemRequest extends BaseRequest
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
            'file' => ['description' => 'File Excel để import công việc (xlsx, xls, csv, tối đa 10MB).'],
        ];
    }
}
