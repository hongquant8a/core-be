<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Follower của Zalo Official Account, sync từ API getfollowers + getprofile.
 *
 * Field `user_id` = Zalo user_id (string), dùng làm recipient khi gửi tin nhắn qua
 * /v2.0/oa/message — link 1:1 với User trong hệ thống qua users.zalo_user_id.
 */
class ZaloOaFollower extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'display_name',
        'user_alias',
        'avatar',
        'raw_profile',
        'last_synced_at',
    ];

    protected $casts = [
        'raw_profile' => 'array',
        'last_synced_at' => 'datetime',
    ];
}
