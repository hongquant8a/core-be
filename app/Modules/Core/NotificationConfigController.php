<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Requests\StoreNotificationScheduleRequest;
use App\Modules\Core\Requests\UpdateNotificationEventConfigRequest;
use App\Modules\Core\Requests\UpdateNotificationScheduleRequest;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * @group Core - Notification Config
 */
class NotificationConfigController extends Controller
{
    public function modules()
    {
        $modules = collect(NotificationModuleEnum::cases())->map(fn ($module) => [
            'key' => $module->value,
            'label' => $module->label(),
            'events' => collect($module->events())->map(fn (NotificationEventEnum $e) => [
                'key' => $e->value,
                'label' => $e->label(),
                'is_reminder' => str_contains($e->value, 'reminder_'),
            ])->all(),
        ])->all();

        return $this->success($modules);
    }

    /**
     * List event configs kèm schedules eager-load, scoped theo organization hiện tại.
     * Non-reminder event có 1 schedule instant; reminder event có N schedule.
     */
    public function eventConfigIndex(Request $request)
    {
        return $this->success(
            NotificationEventConfig::with('schedules')
                ->forModule($this->moduleKey($request))
                ->forOrganization($this->currentOrganizationId())
                ->orderBy('event_key')
                ->get()
        );
    }

    public function eventConfigUpdate(UpdateNotificationEventConfigRequest $request, string $eventKey)
    {
        $cfg = NotificationEventConfig::forModule($this->moduleKey($request))
            ->forOrganization($this->currentOrganizationId())
            ->where('event_key', $eventKey)
            ->firstOrFail();
        $cfg->update($request->validated());

        return $this->success($cfg->fresh());
    }

    public function scheduleIndex(Request $request, string $eventKey)
    {
        $cfg = NotificationEventConfig::forModule($this->moduleKey($request))
            ->forOrganization($this->currentOrganizationId())
            ->where('event_key', $eventKey)
            ->firstOrFail();

        return $this->success(
            $cfg->schedules()->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function scheduleStore(StoreNotificationScheduleRequest $request, string $eventKey)
    {
        $cfg = NotificationEventConfig::forModule($this->moduleKey($request))
            ->forOrganization($this->currentOrganizationId())
            ->where('event_key', $eventKey)
            ->firstOrFail();

        // Non-reminder event: moment/offset bị force về null
        $isReminder = str_contains($eventKey, 'reminder_');
        $data = $request->validated() + ['notification_event_config_id' => $cfg->id];
        if (! $isReminder) {
            $data['moment'] = null;
            $data['offset_minutes'] = null;
        }

        $schedule = NotificationSchedule::create($data);

        return $this->success($schedule, 'Đã tạo schedule', 201);
    }

    public function scheduleUpdate(UpdateNotificationScheduleRequest $request, NotificationSchedule $schedule)
    {
        $this->authorizeScheduleOrg($schedule);
        $schedule->update($request->validated());

        return $this->success($schedule->fresh());
    }

    public function scheduleDestroy(NotificationSchedule $schedule)
    {
        $this->authorizeScheduleOrg($schedule);
        $schedule->delete();

        return $this->success(null, 'Đã xóa schedule');
    }

    private function moduleKey(Request $request): string
    {
        $key = $request->attributes->get('notification_module_key');
        if (! $key) {
            abort(500, 'Route chưa áp middleware notification.module');
        }

        return $key;
    }

    private function currentOrganizationId(): int
    {
        $teamId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        if (! $teamId) {
            throw new RuntimeException('Organization context required. Set X-Team-ID header or equivalent.');
        }

        return (int) $teamId;
    }

    private function authorizeScheduleOrg(NotificationSchedule $schedule): void
    {
        $schedule->loadMissing('eventConfig:id,organization_id');
        if ((int) $schedule->eventConfig->organization_id !== $this->currentOrganizationId()) {
            abort(404);
        }
    }
}
