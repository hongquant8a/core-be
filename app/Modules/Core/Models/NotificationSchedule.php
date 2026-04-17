<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSchedule extends Model
{
    protected $table = 'notification_schedules';

    protected $fillable = [
        'module_key',
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

    public function scopeGlobal($query)
    {
        return $query->whereNull('module_key');
    }

    public function scopeForModule($query, string $moduleKey)
    {
        return $query->where('module_key', $moduleKey);
    }
}
