<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Schedule thuộc về 1 event_config. Giữ channels.
 * - Non-reminder event: 1 schedule duy nhất với moment=null, offset_minutes=null (instant)
 * - Reminder event: N schedule với moment=before/on/after + offset_minutes
 */
class NotificationSchedule extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Core\Models\NotificationScheduleFactory::new();
    }

    protected $table = 'notification_schedules';

    protected $fillable = [
        'notification_event_config_id',
        'moment',
        'offset_minutes',
        'channels',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'channels' => 'array',
    ];

    public function eventConfig()
    {
        return $this->belongsTo(NotificationEventConfig::class, 'notification_event_config_id');
    }
}
