<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends TenantModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Core\Models\NotificationFactory::new();
    }

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'organization_id',
        'event_key',
        'notifiable_type',
        'notifiable_id',
        'title',
        'body',
        'context',
        'read_at',
    ];

    protected $casts = [
        'context' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
