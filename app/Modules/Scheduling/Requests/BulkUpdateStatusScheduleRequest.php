<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatusScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:schedules,id',
            'status' => ['required', 'integer', ScheduleStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'array' => ':attribute phải là một danh sách.',
            'integer' => ':attribute phải là số nguyên.',
            'exists' => ':attribute không tồn tại.',
            'min' => ':attribute tối thiểu phải có :min phần tử.',
            'in' => ':attribute không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Danh sách ID lịch công tác',
            'ids.*' => 'Mã lịch công tác',
            'status' => 'Trạng thái mới',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Mảng các ID lịch công tác cần cập nhật.',
                'example' => [1, 2, 3],
            ],
            'status' => [
                'description' => 'Trạng thái mới (0: Nháp, 1: Chờ duyệt, 2: Đã duyệt/Công bố, 3: Đã hủy).',
                'example' => 2,
            ],
        ];
    }
}
