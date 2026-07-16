<?php

namespace App\Modules\Beneficiary\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StatusHistory extends TenantModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\StatusHistoryFactory::new();
    }

    protected $table = 'beneficiary_status_histories';

    public $timestamps = true;

    protected $fillable = [
        'subject_type', 'subject_id', 'old_status', 'new_status', 'reason',
        'changed_by', 'changed_at', 'organization_id',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function subject()
    {
        return $this->morphTo();
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['subject_type'] ?? null, fn ($q, $type) => $q->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn ($q, $id) => $q->where('subject_id', $id))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('changed_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('changed_at', '<=', $date))
            ->orderByDesc('changed_at');
    }
}
