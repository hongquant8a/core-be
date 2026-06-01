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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }
}
