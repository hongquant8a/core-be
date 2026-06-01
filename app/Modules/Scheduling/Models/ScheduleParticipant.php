<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;

class ScheduleParticipant extends TenantModel
{
    protected $table = 'schedule_participants';

    protected $fillable = [
        'schedule_id', 'organization_id', 'user_id',
        'display_name', 'position_name', 'is_external', 'sort_order',
    ];

    protected $casts = [
        'is_external' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(\App\Modules\Scheduling\Models\SchedulingEmployeeGroup::class, 'user_id')->whereNull('id');
    }
}
