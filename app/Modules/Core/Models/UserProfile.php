<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $table = 'user_profiles';

    protected $fillable = [
        'user_id',
        'phone',
        'gender',
        'birth_date',
        'citizen_id',
        'permanent_address',
        'temporary_address',
        'telegram_chat_id',
        'telegram_link_token',
        'telegram_token_expires_at',
        'telegram_linked_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'telegram_token_expires_at' => 'datetime',
        'telegram_linked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
