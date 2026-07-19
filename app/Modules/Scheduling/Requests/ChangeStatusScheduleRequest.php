<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class ChangeStatusScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:DRAFT,PUBLISHED,0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái không được để trống.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'status' => 'Trạng thái',
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
