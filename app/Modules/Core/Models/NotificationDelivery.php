<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Core\Models\NotificationDeliveryFactory::new();
    }

    protected $table = 'notification_deliveries';

    protected $fillable = [
        'notification_id',
        'channel',
        'status',
        'message_id',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
