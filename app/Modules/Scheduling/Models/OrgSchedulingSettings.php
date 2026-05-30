<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrgSchedulingSettings extends Model
{
    use HasFactory;

    protected $table = 'org_scheduling_settings';

    protected $fillable = [
        'organization_id',
        'executive_approval_required',
        'office_approval_required',
        'executive_approver_roles',
        'office_approver_roles',
        'updated_by',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'executive_approval_required' => 'boolean',
        'office_approval_required' => 'boolean',
        'executive_approver_roles' => 'array',
        'office_approver_roles' => 'array',
        'updated_by' => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (OrgSchedulingSettings $model) {
            $model->updated_by = auth()->id();
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
