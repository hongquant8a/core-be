<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleNotificationRecipient extends Model
{
    use HasFactory;

    protected $table = 'schedule_notification_recipients';

    protected $fillable = [
        'schedule_id',
        'user_id',
        'group_id',
    ];

    protected $casts = [
        'schedule_id' => 'integer',
        'user_id' => 'integer',
        'group_id' => 'integer',
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
