<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id'      => ['sometimes', 'string', 'max:255'],
            'variable_mapping' => ['nullable', 'array'],
            'is_default'       => ['nullable', 'boolean'],
            'status'           => ['nullable', 'string', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'template_id.max'    => 'Template ID không được vượt quá 255 ký tự.',
            'is_default.boolean' => 'is_default phải là true/false.',
            'status.in'          => 'Trạng thái phải là active hoặc inactive.',
        ];
    }

    public function attributes(): array
    {
        return [
            'template_id'      => 'Template ID',
            'variable_mapping' => 'Variable mapping',
            'is_default'       => 'Mặc định',
            'status'           => 'Trạng thái',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'template_id' => [
                'description' => 'ZNS template ID từ Zalo',
                'example' => '263628',
            ],
            'variable_mapping' => [
                'description' => 'Map biến BE → ZNS template variable name',
                'example' => '{"customer_name":"dai_bieu","meeting_title":"ky_hop"}',
            ],
            'is_default' => [
                'description' => 'Template mặc định cho event+channel',
                'example' => true,
            ],
            'status' => [
                'description' => 'Trạng thái',
                'example' => 'active',
            ],
        ];
    }
}
