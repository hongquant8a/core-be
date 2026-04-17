<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSchedule extends Model
{
    protected $table = 'notification_schedules';

    protected $fillable = [
        'configurable_type',
        'configurable_id',
        'moment',
        'offset_minutes',
        'channels',
        'enabled',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'channels' => 'array',
        'enabled' => 'boolean',
    ];

    public function configurable()
    {
        return $this->morphTo();
    }

    /**
     * Scope: global schedules (configurable_type = null).
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('configurable_type')->whereNull('configurable_id');
    }
}
