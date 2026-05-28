<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyNotificationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:notifications,id',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách thông báo không được để trống.',
            'ids.array' => 'Danh sách thông báo phải là một mảng.',
            'ids.min' => 'Danh sách thông báo phải có ít nhất 1 bản ghi.',
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
