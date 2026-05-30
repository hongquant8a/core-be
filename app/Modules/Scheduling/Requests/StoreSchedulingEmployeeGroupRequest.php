<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreSchedulingEmployeeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['required', StatusEnum::rule()],
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => [
                'required',
                'integer',
                'exists:scheduling_employees,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên nhóm.',
            'name.max' => 'Tên nhóm không được vượt quá 255 ký tự.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'employee_ids.array' => 'Danh sách nhân viên phải là dạng mảng.',
            'employee_ids.*.exists' => 'Một hoặc nhiều nhân viên được chọn không tồn tại trong module.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên nhóm',
            'description' => 'mô tả',
            'status' => 'trạng thái',
            'employee_ids' => 'danh sách nhân viên',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Tên nhóm nhân viên.',
                'example' => 'Tổ Tổng hợp Lịch Ban Giám Đốc',
            ],
            'description' => [
                'description' => 'Mô tả chi tiết nhóm.',
                'example' => 'Bao gồm các cán bộ trực tiếp phục vụ và liên hệ lịch cho Ban Giám đốc.',
            ],
            'status' => [
                'description' => 'Trạng thái hoạt động.',
                'example' => StatusEnum::Active->value,
            ],
            'employee_ids' => [
                'description' => 'Mảng chứa ID của các nhân viên thuộc nhóm (từ bảng scheduling_employees).',
                'example' => [1, 2, 3],
            ],
        ];
    }
}
