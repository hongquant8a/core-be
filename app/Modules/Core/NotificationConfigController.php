<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Requests\StoreNotificationScheduleRequest;
use App\Modules\Core\Requests\UpdateNotificationEventConfigRequest;
use App\Modules\Core\Requests\UpdateNotificationScheduleRequest;

/**
 * @group Core - Notification Config
 */
class NotificationConfigController extends Controller
{
    public function eventConfigIndex()
    {
        // Phase A: chỉ thao tác global configs (configurable_type=null).
        // Phase C sẽ thêm per-entity config endpoints.
        return $this->success(NotificationEventConfig::global()->orderBy('event_key')->get());
    }

    public function eventConfigUpdate(UpdateNotificationEventConfigRequest $request, string $eventKey)
    {
        $cfg = NotificationEventConfig::global()->where('event_key', $eventKey)->firstOrFail();
        $cfg->update($request->validated());

        return $this->success($cfg->fresh());
    }

    public function scheduleIndex()
    {
        return $this->success(NotificationSchedule::global()->orderBy('sort_order')->orderBy('id')->get());
    }

    public function scheduleStore(StoreNotificationScheduleRequest $request)
    {
        // Tạo global schedule (configurable null). Per-entity tạo qua API Phase C.
        $schedule = NotificationSchedule::create($request->validated() + [
            'configurable_type' => null,
            'configurable_id' => null,
        ]);

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
}
