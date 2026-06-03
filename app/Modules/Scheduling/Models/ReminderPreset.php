<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Core\Models\Organization;

class ReminderPreset extends Model
{
    protected $table = 'reminder_presets';

    protected $fillable = [
        'organization_id', 'name', 'minutes_before', 'channels', 'is_default', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'channels'       => 'array',
        'is_default'     => 'boolean',
        'is_active'      => 'boolean',
        'minutes_before' => 'integer',
        'sort_order'     => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
