<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Core\Enums\StatusEnum;
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
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['nullable', StatusEnum::rule()],
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
            'name.max' => 'Tên nhóm không được vượt quá 255 ký tự.',
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
                'example' => 'Tổ Tổng hợp Lịch Văn phòng',
            ],
            'description' => [
                'description' => 'Mô tả chi tiết nhóm.',
                'example' => 'Cập nhật lại danh sách thành viên phục vụ khối văn phòng.',
            ],
            'status' => [
                'description' => 'Trạng thái hoạt động.',
                'example' => StatusEnum::Inactive->value,
            ],
            'employee_ids' => [
                'description' => 'Mảng chứa ID mới của các nhân viên thuộc nhóm.',
                'example' => [2, 4],
            ],
        ];
    }
}
