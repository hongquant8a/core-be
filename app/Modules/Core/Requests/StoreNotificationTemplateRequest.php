<?php

namespace App\Modules\Core\Requests;

use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_key'       => ['required', 'string', NotificationModuleEnum::rule()],
            'event_key'        => ['nullable', 'string', NotificationEventEnum::rule()],
            'channel'          => ['required', 'string', 'in:zalo_zns'],
            'template_id'      => ['required', 'string', 'max:255'],
            'variable_mapping' => ['nullable', 'array'],
            'organization_id'  => ['nullable', 'integer', 'exists:organizations,id'],
            'is_default'       => ['nullable', 'boolean'],
            'status'           => ['nullable', 'string', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'module_key.required'       => 'Module không được để trống.',
            'module_key.in'             => 'Module không hợp lệ.',
            'event_key.in'              => 'Event không hợp lệ.',
            'channel.required'          => 'Kênh gửi không được để trống.',
            'channel.in'                => 'Kênh gửi không hợp lệ.',
            'template_id.required'      => 'Template ID không được để trống.',
            'template_id.max'           => 'Template ID không được vượt quá 255 ký tự.',
            'organization_id.exists'    => 'Tổ chức không tồn tại.',
            'is_default.boolean'        => 'is_default phải là true/false.',
            'status.in'                 => 'Trạng thái phải là active hoặc inactive.',
        ];
    }

    public function attributes(): array
    {
        return [
            'module_key'       => 'Module',
            'event_key'        => 'Event',
            'channel'          => 'Kênh gửi',
            'template_id'      => 'Template ID',
            'variable_mapping' => 'Variable mapping',
            'organization_id'  => 'Tổ chức',
            'is_default'       => 'Mặc định',
            'status'           => 'Trạng thái',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'module_key' => [
                'description' => 'Module key',
                'example' => 'meeting',
            ],
            'event_key' => [
                'description' => 'Event key',
                'example' => 'meeting_published',
            ],
            'channel' => [
                'description' => 'Kênh gửi',
                'example' => 'zalo_zns',
            ],
            'template_id' => [
                'description' => 'ZNS template ID từ Zalo',
                'example' => '263628',
            ],
            'variable_mapping' => [
                'description' => 'Map biến BE → ZNS template variable name',
                'example' => '{"customer_name":"dai_bieu","meeting_title":"ky_hop"}',
            ],
            'organization_id' => [
                'description' => 'ID tổ chức (null = system default)',
                'example' => 1,
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
