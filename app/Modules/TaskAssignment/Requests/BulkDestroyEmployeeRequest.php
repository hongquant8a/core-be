<?php

namespace App\Modules\TaskAssignment\Requests;

class BulkDestroyEmployeeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Vui lòng chọn nhân viên cần xóa.',
            'ids.array' => 'Danh sách nhân viên không hợp lệ.',
            'ids.min' => 'Chọn ít nhất 1 nhân viên.',
            'ids.*.integer' => 'ID nhân viên phải là số nguyên.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'danh sách nhân viên',
            'ids.*' => 'ID nhân viên',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Danh sách ID nhân viên cần xóa.',
                'example' => [1, 2, 3],
            ],
        ];
    }
}
