<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyLogActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:log_activities,id',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách nhật ký không được để trống.',
            'ids.array' => 'Danh sách nhật ký phải là một mảng.',
            'ids.min' => 'Danh sách nhật ký phải có ít nhất 1 bản ghi.',
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
