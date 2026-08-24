<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Models\TaskAssignmentEmployeeDepartment;

class StoreTransferRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'to_user_id' => 'required|integer|exists:users,id',
            // Phòng ban ghi vào dòng phân công mới. Trước đây hệ thống tự đoán bằng cờ
            // `is_primary` — cờ này không có UI để đặt nên kết quả phụ thuộc thứ tự thao tác.
            // Nay: người nhận thuộc đúng 1 phòng thì suy ra được, thuộc nhiều phòng thì phải chọn.
            'to_department_id' => [
                'nullable',
                'integer',
                'exists:task_assignment_departments,id',
                function ($attribute, $value, $fail) {
                    $userId = $this->input('to_user_id');
                    if (! $userId) {
                        return;
                    }

                    $deptIds = TaskAssignmentEmployeeDepartment::forUser($userId)
                        ->activeEmployee()
                        ->pluck('task_assignment_department_id')
                        ->all();

                    if ($value === null) {
                        if (count($deptIds) > 1) {
                            $fail('Người nhận thuộc nhiều phòng ban, vui lòng chọn phòng ban tiếp nhận.');
                        }

                        return;
                    }

                    if (! in_array((int) $value, array_map('intval', $deptIds), true)) {
                        $fail('Người nhận không thuộc phòng ban đã chọn.');
                    }
                },
            ],
            'note' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'to_user_id.required' => 'Vui lòng chọn người nhận.',
            'to_user_id.integer' => 'Người nhận không hợp lệ.',
            'to_user_id.exists' => 'Người nhận không tồn tại.',
            'to_department_id.integer' => 'Phòng ban tiếp nhận không hợp lệ.',
            'to_department_id.exists' => 'Phòng ban tiếp nhận không tồn tại.',
            'note.string' => 'Ghi chú phải là chuỗi ký tự.',
            'note.max' => 'Ghi chú không được vượt quá 200 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'to_user_id' => 'Người nhận',
            'to_department_id' => 'Phòng ban tiếp nhận',
            'note' => 'Ghi chú',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'to_user_id' => [
                'description' => 'ID người nhận công việc.',
                'example' => 5,
            ],
            'to_department_id' => [
                'description' => 'ID phòng ban tiếp nhận. Bắt buộc khi người nhận thuộc nhiều phòng ban; bỏ trống nếu chỉ thuộc một phòng.',
                'example' => 3,
            ],
            'note' => [
                'description' => 'Ghi chú điều chuyển (tối đa 200 ký tự).',
                'example' => 'Chuyển do đồng chí A đi công tác.',
            ],
        ];
    }
}
