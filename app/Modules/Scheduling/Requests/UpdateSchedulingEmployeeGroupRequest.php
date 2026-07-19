<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchedulingEmployeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['nullable', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'status'       => ['nullable', \App\Modules\Core\Enums\StatusEnum::rule()],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'employee_ids'   => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:scheduling_employees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Tên nhóm phải là chuỗi ký tự.',
            'name.max' => 'Tên nhóm không được vượt quá 255 ký tự.',
            'description.string' => 'Mô tả phải là chuỗi ký tự.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'sort_order.integer' => 'Thứ tự sắp xếp phải là số nguyên.',
            'sort_order.min' => 'Thứ tự sắp xếp không được nhỏ hơn 0.',
            'employee_ids.array' => 'Danh sách nhân viên phải là một mảng.',
            'employee_ids.*.integer' => 'ID nhân viên phải là số nguyên.',
            'employee_ids.*.exists' => 'ID nhân viên không tồn tại trong hệ thống.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên nhóm',
            'description' => 'Mô tả',
            'status' => 'Trạng thái',
            'sort_order' => 'Thứ tự sắp xếp',
            'employee_ids' => 'Danh sách nhân viên',
            'employee_ids.*' => 'ID nhân viên',
        ];
    }
}
