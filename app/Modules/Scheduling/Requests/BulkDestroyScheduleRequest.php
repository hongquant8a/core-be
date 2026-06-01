<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'exists:schedules,id'],
        ];
    }
}
