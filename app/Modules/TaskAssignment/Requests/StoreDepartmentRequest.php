<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['required', StatusEnum::rule()],
            'sort_order' => 'nullable|integer|min:0',
            'is_petition_overview' => 'boolean',
            // Thành viên phòng ban là một trường của chính phòng ban — không có endpoint quan hệ riêng.
            // Gửi mảng rỗng để xoá hết thành viên; không gửi khoá này thì giữ nguyên.
            'employee_ids' => 'sometimes|array',
            'employee_ids.*' => [
                'integer',
                Rule::exists('task_assignment_employees', 'id')
                    ->where(fn ($q) => $q->where('organization_id', getPermissionsTeamId())->where('status', 'active')),
            ],
            'representative_employee_id' => ['nullable', 'integer', Rule::in($this->input('employee_ids', []))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên phòng ban.',
            'name.max' => 'Tên phòng ban không được vượt quá 255 ký tự.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'employee_ids.array' => 'Danh sách nhân viên không hợp lệ.',
            'employee_ids.*.integer' => 'ID nhân viên phải là số nguyên.',
            'employee_ids.*.exists' => 'Có nhân viên không tồn tại hoặc đã bị vô hiệu hóa.',
            'representative_employee_id.in' => 'Người đại diện phải nằm trong danh sách nhân viên đã chọn.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Tên phòng ban.',
                'example' => 'Phòng Kế toán',
            ],
            'description' => [
                'description' => 'Mô tả phòng ban.',
                'example' => 'Phòng quản lý tài chính kế toán.',
            ],
            'status' => [
                'description' => 'Trạng thái phòng ban.',
                'example' => StatusEnum::Active->value,
            ],
            'sort_order' => [
                'description' => 'Thứ tự sắp xếp.',
                'example' => 1,
            ],
            'is_petition_overview' => [
                'description' => 'Phòng ban tổng hợp đơn thư, được xem toàn bộ đơn thư.',
                'example' => false,
            ],
            'employee_ids' => [
                'description' => 'Danh sách ID nhân viên thuộc phòng ban (task_assignment_employees.id). Gửi mảng rỗng để xoá hết.',
                'example' => [3, 7],
            ],
            'representative_employee_id' => [
                'description' => 'ID nhân viên làm người đại diện phòng ban. Phải nằm trong employee_ids.',
                'example' => 3,
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên',
            'description' => 'Mô tả',
            'status' => 'Trạng thái',
            'sort_order' => 'Thứ tự sắp xếp',
            'is_petition_overview' => 'Tổng hợp đơn thư',
            'employee_ids' => 'Danh sách nhân viên',
            'representative_employee_id' => 'Người đại diện',
        ];
    }
}
