<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;

class SchedulingFilterPreset extends Model
{
    protected $table = 'scheduling_filter_presets';

    protected $fillable = [
        'organization_id', 'user_id', 'name', 'filters', 'is_default',
    ];

    protected $casts = [
        'filters'    => 'array',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
