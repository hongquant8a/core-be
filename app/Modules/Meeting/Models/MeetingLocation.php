<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'google_maps_url',
        'description',
        'status',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected static function booted()
    {
        static::creating(fn (MeetingLocation $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (MeetingLocation $model) => $model->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeFilter($query, array $filters)
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        $query->when($organizationId, function ($q, $organizationId) {
            $q->where(function ($sub) use ($organizationId) {
                $sub->where('organization_id', (int) $organizationId)
                    ->orWhereNull('organization_id');
            });
        })->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'sort_order', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'name', 'sort_order', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed, true) ? $sortBy : 'sort_order';
                $q->orderBy($column, $filters['sort_order'] ?? 'asc');
            });
    }
}
