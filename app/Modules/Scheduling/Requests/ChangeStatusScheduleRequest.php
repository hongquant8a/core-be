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
            'status' => ['required', ScheduleStatusEnum::rule()],
        ];
    }
}
