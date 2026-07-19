<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchedulingEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'         => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('scheduling_employees', 'user_id')
                    ->where('organization_id', getPermissionsTeamId() ?: $this->header('X-Organization-Id'))
                    ->whereNull('deleted_at')
            ],
            'name'            => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'position_name'   => ['nullable', 'string', 'max:255'],
            'department'      => ['nullable', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:255'],
            'priority_weight' => ['nullable', 'integer', 'min:0'],
            'status'          => ['nullable', \App\Modules\Core\Enums\StatusEnum::rule()],
            'sort_order'      => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.integer' => 'Người dùng phải là số nguyên.',
            'user_id.exists' => 'Người dùng không tồn tại trong hệ thống.',
            'user_id.unique' => 'Người dùng này đã được thêm làm nhân viên lịch trực.',
            'name.required_without' => 'Tên không được để trống khi không chọn người dùng.',
            'name.string' => 'Tên phải là chuỗi ký tự.',
            'name.max' => 'Tên không được vượt quá 255 ký tự.',
            'position_name.string' => 'Chức danh phải là chuỗi ký tự.',
            'position_name.max' => 'Chức danh không được vượt quá 255 ký tự.',
            'department.string' => 'Phòng ban phải là chuỗi ký tự.',
            'department.max' => 'Phòng ban không được vượt quá 255 ký tự.',
            'phone.string' => 'Số điện thoại phải là chuỗi ký tự.',
            'phone.max' => 'Số điện thoại không được vượt quá 30 ký tự.',
            'email.email' => 'Email không đúng định dạng.',
            'email.max' => 'Email không được vượt quá 255 ký tự.',
            'priority_weight.integer' => 'Trọng số ưu tiên phải là số nguyên.',
            'priority_weight.min' => 'Trọng số ưu tiên không được nhỏ hơn 0.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'sort_order.integer' => 'Thứ tự sắp xếp phải là số nguyên.',
            'sort_order.min' => 'Thứ tự sắp xếp không được nhỏ hơn 0.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'Người dùng',
            'name' => 'Tên',
            'position_name' => 'Chức danh',
            'department' => 'Phòng ban',
            'phone' => 'Số điện thoại',
            'email' => 'Email',
            'priority_weight' => 'Trọng số ưu tiên',
            'status' => 'Trạng thái',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
}
