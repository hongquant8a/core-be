<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;

class NotificationGroup extends TenantModel
{
    protected $table = 'notification_groups';

    protected $fillable = [
        'organization_id', 'name', 'description', 'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'notification_group_members', 'group_id', 'user_id');
    }
}
