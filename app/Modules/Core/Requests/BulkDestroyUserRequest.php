<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyUserRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách người dùng không được để trống.',
            'ids.array' => 'Danh sách người dùng phải là một mảng.',
            'ids.min' => 'Danh sách người dùng phải có ít nhất 1 người dùng.',
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
        ];
    }
}
