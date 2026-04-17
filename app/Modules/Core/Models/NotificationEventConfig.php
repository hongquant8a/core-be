<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationEventConfig extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Core\Models\NotificationEventConfigFactory::new();
    }

    protected $table = 'notification_event_configs';

    protected $fillable = [
        'module_key',
        'organization_id',
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

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForModule($query, string $moduleKey)
    {
        return $query->where('module_key', $moduleKey);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
