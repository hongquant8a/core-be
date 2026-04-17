<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEventConfig extends Model
{
    protected $table = 'notification_event_configs';

    protected $fillable = [
        'configurable_type',
        'configurable_id',
        'event_key',
        'enabled',
        'channels',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'channels' => 'array',
    ];

    public function configurable()
    {
        return $this->morphTo();
    }

    /**
     * Scope: global configs (configurable_type = null).
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('configurable_type')->whereNull('configurable_id');
    }
}
