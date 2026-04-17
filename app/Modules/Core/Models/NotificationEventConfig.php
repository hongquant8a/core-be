<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEventConfig extends Model
{
    protected $table = 'notification_event_configs';

    protected $fillable = [
        'module_key',
        'event_key',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function schedules()
    {
        return $this->hasMany(NotificationSchedule::class, 'notification_event_config_id');
    }

    /**
     * Scope: global configs (module_key = null).
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('module_key');
    }

    /**
     * Scope: configs của module cụ thể.
     */
    public function scopeForModule($query, string $moduleKey)
    {
        return $query->where('module_key', $moduleKey);
    }
}
