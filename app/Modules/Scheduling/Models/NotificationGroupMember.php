<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Core\Models\User;

class NotificationGroupMember extends Model
{
    public $timestamps = false;

    protected $table = 'notification_group_members';

    protected $fillable = [
        'group_id', 'user_id',
    ];

    public function group()
    {
        return $this->belongsTo(NotificationGroup::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
