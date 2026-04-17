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

/**
 * @group Core - Notification Config
 *
 * Controller dùng chung cho mọi module. Module_key được middleware
 * `notification.module:{key}` gán vào request attributes — controller
 * không nhận trực tiếp từ URL.
 */
class NotificationConfigController extends Controller
{
    /**
     * Registry module + events — mục đích nội bộ/dashboard (không bắt buộc cho FE).
     */
    public function modules()
    {
        $modules = collect(NotificationModuleEnum::cases())->map(fn ($module) => [
            'key' => $module->value,
            'label' => $module->label(),
            'events' => collect($module->events())->map(fn (NotificationEventEnum $e) => [
                'key' => $e->value,
                'label' => $e->label(),
            ])->all(),
        ])->all();

        return $this->success($modules);
    }

    public function eventConfigIndex(Request $request)
    {
        return $this->success(
            NotificationEventConfig::forModule($this->moduleKey($request))
                ->orderBy('event_key')
                ->get()
        );
    }

    public function eventConfigUpdate(UpdateNotificationEventConfigRequest $request, string $eventKey)
    {
        $cfg = NotificationEventConfig::forModule($this->moduleKey($request))
            ->where('event_key', $eventKey)
            ->firstOrFail();
        $cfg->update($request->validated());

        return $this->success($cfg->fresh());
    }

    public function scheduleIndex(Request $request)
    {
        return $this->success(
            NotificationSchedule::forModule($this->moduleKey($request))
                ->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function scheduleStore(StoreNotificationScheduleRequest $request)
    {
        $schedule = NotificationSchedule::create(
            $request->validated() + ['module_key' => $this->moduleKey($request)]
        );

        return $this->success($schedule, 'Đã tạo lịch nhắc', 201);
    }

    public function scheduleUpdate(UpdateNotificationScheduleRequest $request, NotificationSchedule $schedule)
    {
        $schedule->update($request->validated());

        return $this->success($schedule->fresh());
    }

    public function scheduleDestroy(NotificationSchedule $schedule)
    {
        $schedule->delete();

        return $this->success(null, 'Đã xóa lịch nhắc');
    }

    private function moduleKey(Request $request): string
    {
        $key = $request->attributes->get('notification_module_key');
        if (! $key) {
            abort(500, 'Route chưa áp middleware notification.module');
        }

        return $key;
    }
}
