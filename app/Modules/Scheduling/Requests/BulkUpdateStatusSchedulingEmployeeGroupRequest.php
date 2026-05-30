<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatusSchedulingEmployeeGroupRequest extends FormRequest
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
            'status' => ['required', StatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Vui lòng cung cấp danh sách ID cần cập nhật.',
            'ids.array' => 'Danh sách ID phải là dạng mảng.',
            'ids.*.exists' => 'Một hoặc nhiều nhóm nhân viên không tồn tại trong hệ thống.',
            'status.required' => 'Vui lòng chọn trạng thái mới.',
            'status.in' => 'Trạng thái mới không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'danh sách ID',
            'status' => 'trạng thái',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Mảng các ID nhóm nhân viên lịch công tác cần cập nhật.',
                'example' => [1, 2, 3],
            ],
            'status' => [
                'description' => 'Trạng thái mới áp dụng cho danh sách nhóm.',
                'example' => StatusEnum::Active->value,
            ],
        ];
    }
}
