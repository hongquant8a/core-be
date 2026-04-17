<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
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

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
