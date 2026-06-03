<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Enums\{NotificationChannel, NotificationStatus};

class ScheduleNotification extends Model
{
    protected $table = 'schedule_notifications';

    protected $fillable = [
        'organization_id', 'schedule_id', 'user_id', 'channel', 'remind_at',
        'status', 'retry_count', 'external_message_id', 'error_message', 'sent_at',
    ];

    protected $casts = [
        'remind_at'   => 'datetime',
        'sent_at'     => 'datetime',
        'status'      => NotificationStatus::class,
        'channel'     => NotificationChannel::class,
        'retry_count' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
