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
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:schedules,id',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'array' => ':attribute phải là một danh sách.',
            'integer' => ':attribute phải là số nguyên.',
            'exists' => ':attribute không tồn tại.',
            'min' => ':attribute tối thiểu phải có :min phần tử.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Danh sách ID lịch công tác',
            'ids.*' => 'Mã lịch công tác',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Mảng các ID lịch công tác cần xóa.',
                'example' => [1, 2, 3],
            ],
        ];
    }
}
