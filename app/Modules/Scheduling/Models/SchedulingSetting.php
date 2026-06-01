<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulingSetting extends Model
{
    protected $table = 'scheduling_settings';

    protected $fillable = [
        'organization_id', 'approval_enabled', 'approval_module_types',
        'default_channels', 'working_sessions',
    ];

    protected $casts = [
        'approval_enabled'      => 'boolean',
        'approval_module_types' => 'array',
        'default_channels'      => 'array',
        'working_sessions'      => 'array',
    ];
}
