<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ordered_ids'   => ['required', 'array'],
            'ordered_ids.*' => ['integer', 'exists:schedules,id'],
        ];
    }
}
