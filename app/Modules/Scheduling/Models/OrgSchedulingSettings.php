<?php

namespace App\Modules\Scheduling\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Core\Models\Organization;

class OrgSchedulingSettings extends Model
{
    protected $table = 'org_scheduling_settings';

    protected $fillable = [
        'organization_id', 'executive_requires_approval', 'office_requires_approval',
        'executive_working_sessions', 'office_working_sessions',
    ];

    protected $casts = [
        'executive_requires_approval' => 'boolean',
        'office_requires_approval'    => 'boolean',
        'executive_working_sessions'  => 'array',
        'office_working_sessions'     => 'array',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
