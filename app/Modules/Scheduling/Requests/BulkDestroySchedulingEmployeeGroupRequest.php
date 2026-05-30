<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroySchedulingEmployeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:scheduling_employee_groups,id',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Vui lòng cung cấp danh sách ID cần xóa.',
            'ids.array' => 'Danh sách ID phải là dạng mảng.',
            'ids.*.exists' => 'Một hoặc nhiều nhóm nhân viên không tồn tại trong hệ thống.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'danh sách ID',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Mảng các ID nhóm nhân viên lịch công tác cần xóa.',
                'example' => [1, 2, 3],
            ],
        ];
    }
}
