<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Core\Models\User;

class ScheduleNotificationRecipient extends Model
{
    public $timestamps = false;

    protected $table = 'schedule_notification_recipients';

    protected $fillable = [
        'schedule_id', 'user_id', 'group_id', 'display_name', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function group()
    {
        return $this->belongsTo(NotificationGroup::class, 'group_id');
    }
}
