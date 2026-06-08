<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulingSetting extends Model
{
    protected $table = 'scheduling_settings';

    protected $fillable = [
        'organization_id', 'default_channels',
    ];

    protected $casts = [
        'default_channels'      => 'array',
    ];
}
