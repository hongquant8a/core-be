<?php

namespace App\Services\Notification\Services;

use App\Modules\Core\Models\NotificationTemplate;
use App\Services\Notification\Enums\NotificationModuleEnum;

class NotificationTemplateService
{
    public function __construct(private ContentBuilderRegistry $registry) {}

    /**
     * Resolve template active cho (organization, module, channel).
     * Mỗi module có 1 template ZNS, event được phân biệt qua biến `event` trong context.
     * Ưu tiên template riêng của organization; fallback template system (organization_id = null).
     */
    public function resolve(int $organizationId, string $moduleKey, string $channel): ?NotificationTemplate
    {
        return NotificationTemplate::where('module_key', $moduleKey)
            ->where('channel', $channel)
            ->where('status', 'active')
            ->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                    ->orWhereNull('organization_id');
            })
            ->orderByRaw('organization_id IS NOT NULL DESC')
            ->first();
    }

    /**
     * Remap BE keys → ZNS template variable names theo variable_mapping.
     */
    public function applyMapping(NotificationTemplate $template, array $beContext): array
    {
        $mapping = $template->variable_mapping;

        if (empty($mapping)) {
            return $beContext;
        }

        $mapped = [];
        foreach ($beContext as $beKey => $value) {
            $znsKey = $mapping[$beKey] ?? $beKey;
            $mapped[$znsKey] = $value;
        }

        return $mapped;
    }

    /**
     * Trả về biến BE khả dụng + danh sách event của một module.
     * Variables được gộp chung (union) vì các event trong cùng module dùng chung biến.
     * Dùng cho FE khi cấu hình variable_mapping.
     */
    public function getVariables(string $moduleKey): array
    {
        $module = NotificationModuleEnum::from($moduleKey);
        $events = $module->events();

        $variables = [];
        $eventList = [];

        foreach ($events as $event) {
            try {
                $builder = $this->registry->for($event->value);
            } catch (\RuntimeException) {
                continue;
            }

            foreach ($builder->znsVariables() as $key => $desc) {
                $variables[$key] = $desc;
            }

            $eventList[] = [
                'key' => $event->value,
                'label' => $event->label(),
            ];
        }

        return [
            'module_key' => $moduleKey,
            'module_label' => $module->label(),
            'variables' => $variables,
            'events' => $eventList,
        ];
    }
}
