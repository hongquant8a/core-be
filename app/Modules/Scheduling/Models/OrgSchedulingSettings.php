<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Core\Models\Organization;

class OrgSchedulingSettings extends Model
{
    protected $table = 'org_scheduling_settings';

    protected $fillable = [
        'organization_id', 'requires_approval'
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
