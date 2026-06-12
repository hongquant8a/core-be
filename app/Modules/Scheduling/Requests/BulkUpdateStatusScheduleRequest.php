<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatusScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids'    => ['required', 'array'],
            'ids.*'  => ['integer', 'exists:schedules,id'],
            'status' => ['required', 'in:DRAFT,PUBLISHED,0,1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $status = $this->input('status');

        if ($status === 0 || $status === '0') {
            $this->merge(['status' => 'DRAFT']);
        } elseif ($status === 1 || $status === '1') {
            $this->merge(['status' => 'PUBLISHED']);
        }
    }
}
