<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationGroupMember extends Model
{
    use HasFactory;

    protected $table = 'notification_group_members';

    protected $fillable = [
        'group_id',
        'user_id',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'user_id' => 'integer',
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
