<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrgSchedulingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'executive_requires_approval' => ['required', 'boolean'],
            'office_requires_approval'    => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'executive_requires_approval.required' => 'Yêu cầu duyệt (lãnh đạo) không được để trống.',
            'executive_requires_approval.boolean' => 'Yêu cầu duyệt (lãnh đạo) phải là true hoặc false.',
            'office_requires_approval.required' => 'Yêu cầu duyệt (văn phòng) không được để trống.',
            'office_requires_approval.boolean' => 'Yêu cầu duyệt (văn phòng) phải là true hoặc false.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'executive_requires_approval' => 'Yêu cầu duyệt (lãnh đạo)',
            'office_requires_approval' => 'Yêu cầu duyệt (văn phòng)',
        ];
    }
}
