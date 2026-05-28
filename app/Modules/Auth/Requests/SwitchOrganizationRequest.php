<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => 'required|integer|exists:organizations,id',
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.required' => 'Tổ chức là bắt buộc.',
            'organization_id.integer' => 'ID tổ chức phải là số nguyên.',
            'organization_id.exists' => 'Tổ chức không tồn tại.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'organization_id' => [
                'description' => 'ID tổ chức muốn chuyển ngữ cảnh làm việc',
                'example' => 2,
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'organization_id' => 'Tổ chức',
        ];
    }
}
