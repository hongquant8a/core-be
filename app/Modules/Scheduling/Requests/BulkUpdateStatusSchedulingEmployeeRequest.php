<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatusSchedulingEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:scheduling_employees,id',
            'status' => ['required', StatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID không được trống.',
            'ids.array' => 'Danh sách ID phải là dạng mảng.',
            'ids.*.exists' => 'ID nhân viên không tồn tại.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
