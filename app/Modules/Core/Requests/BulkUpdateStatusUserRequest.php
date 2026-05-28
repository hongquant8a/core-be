<?php

namespace App\Modules\Core\Requests;

use App\Modules\Core\Enums\UserStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatusUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:users,id',
            'status' => ['required', 'in:'.implode(',', UserStatusEnum::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách người dùng không được để trống.',
            'ids.array' => 'Danh sách người dùng phải là một mảng.',
            'ids.min' => 'Danh sách người dùng phải có ít nhất 1 người dùng.',
            'status.required' => 'Trạng thái không được để trống.',
            'status.in' => 'Trạng thái không hợp lệ. Chỉ chấp nhận active, inactive, banned.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Danh sách ID',
            'ids.*' => 'ID',
            'status' => 'Trạng thái',
        ];
    }
}
