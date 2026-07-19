<?php

namespace App\Modules\TaskAssignment\Requests;

class BulkDestroyPetitionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:task_assignment_petitions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID không được để trống.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Danh sách ID phải có ít nhất 1 phần tử.',
            'ids.*.integer' => 'Mỗi ID phải là số nguyên.',
            'ids.*.exists' => 'Đơn thư không tồn tại.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Danh sách ID đơn thư cần xóa.',
                'example' => [1, 2, 3],
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Danh sách ID',
            'ids.*' => 'ID',
        ];
    }
}
