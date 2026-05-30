<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Core\Enums\StatusEnum;
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
        $orgId = getPermissionsTeamId();

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('scheduling_employees', 'user_id')
                    ->where(fn ($q) => $q->where('organization_id', $orgId)),
            ],
            'status' => ['required', StatusEnum::rule()],
            'note' => 'nullable|string|max:65535',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Vui lòng chọn người dùng.',
            'user_id.exists' => 'Người dùng không tồn tại.',
            'user_id.unique' => 'Người dùng này đã là nhân viên của module trong tổ chức hiện tại.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'note.max' => 'Ghi chú quá dài.',
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'người dùng',
            'status' => 'trạng thái',
            'note' => 'ghi chú',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'user_id' => [
                'description' => 'ID người dùng (lấy từ danh sách users tổng).',
                'example' => 5,
            ],
            'status' => [
                'description' => 'Trạng thái nhân viên.',
                'example' => StatusEnum::Active->value,
            ],
            'note' => [
                'description' => 'Ghi chú nội bộ.',
                'example' => 'Bổ sung nhân sự điều hành lịch.',
            ],
        ];
    }
}
