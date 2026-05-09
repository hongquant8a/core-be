<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model LogActivity – nhật ký truy cập của người dùng.
 */
class LogActivity extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Core\Models\LogActivityFactory::new();
    }

    protected $table = 'log_activities';

    protected $fillable = [
        'description',
        'user_type',
        'user_id',
        'organization_id',
        'route',
        'method_type',
        'status_code',
        'ip_address',
        'country',
        'user_agent',
        'request_data',
    ];

    protected $casts = [
        'request_data' => 'array',
        'status_code' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($q2) use ($search) {
                $q2->where('description', 'like', '%'.$search.'%')
                    ->orWhere('route', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('country', 'like', '%'.$search.'%')
                    ->orWhere('user_type', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($q3) => $q3->where('name', 'like', '%'.$search.'%'));
            });
        });
        $query->when(isset($filters['organization_id']) && $filters['organization_id'] !== '', fn ($q) => $q->where('organization_id', $filters['organization_id']));
        $query->when(isset($filters['user_id']) && $filters['user_id'] !== '', fn ($q) => $q->where('user_id', $filters['user_id']));
        $query->when(isset($filters['from_date']) && $filters['from_date'], fn ($q) => $q->whereDate('created_at', '>=', $filters['from_date']));
        $query->when(isset($filters['to_date']) && $filters['to_date'], fn ($q) => $q->whereDate('created_at', '<=', $filters['to_date']));
        $query->when(isset($filters['method_type']) && $filters['method_type'], function ($q) use ($filters) {
            // Hỗ trợ alias semantic gom các HTTP method theo loại thao tác — đồng bộ với
            // stats card (PUT + PATCH = "update"). Single method giữ exact match (BC).
            $aliases = [
                'view' => ['GET'],
                'create' => ['POST'],
                'update' => ['PUT', 'PATCH'],
                'delete' => ['DELETE'],
            ];
            $value = strtolower((string) $filters['method_type']);
            if (isset($aliases[$value])) {
                $q->whereIn('method_type', $aliases[$value]);

                return;
            }
            $q->where('method_type', $filters['method_type']);
        });
        $query->when(isset($filters['status_code']) && $filters['status_code'] !== null && $filters['status_code'] !== '', fn ($q) => $q->where('status_code', $filters['status_code']));
        $query->when($filters['sort_by'] ?? 'id', function ($q, $sortBy) use ($filters) {
            $allowed = ['id', 'description', 'route', 'method_type', 'status_code', 'ip_address', 'country', 'created_at'];
            $column = in_array($sortBy, $allowed) ? $sortBy : 'id';
            \App\Modules\Core\Support\VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
        });

        return $query;
    }
}
