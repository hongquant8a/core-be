<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Model Permission (kế thừa Spatie), bổ sung description, sort_order, parent_id, scope filter.
 */
class Permission extends SpatiePermission
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Core\Models\PermissionFactory::new();
    }

    protected $fillable = [
        'name',
        'guard_name',
        'description',
        'sort_order',
        'parent_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(Permission::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Permission::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Scope lọc: search (name, guard_name, description); parent_id; from_date, to_date; sort_by.
     */
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', '%'.$search.'%')
                    ->orWhere('guard_name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        })->when(array_key_exists('parent_id', $filters) && $filters['parent_id'] !== '', function ($q) use ($filters) {
            $q->where('parent_id', $filters['parent_id'] === null ? null : (int) $filters['parent_id']);
        })->when(isset($filters['from_date']) && $filters['from_date'], function ($q, $v) use ($filters) {
            $q->whereDate('created_at', '>=', $filters['from_date']);
        })->when(isset($filters['to_date']) && $filters['to_date'], function ($q, $v) use ($filters) {
            $q->whereDate('created_at', '<=', $filters['to_date']);
        })->when($filters['sort_by'] ?? null, function ($q, $sortBy) use ($filters) {
            $allowed = ['id', 'name', 'guard_name', 'description', 'sort_order', 'parent_id', 'created_at', 'updated_at'];
            $column = in_array($sortBy, $allowed) ? $sortBy : 'id';
            $q->orderBy($column, $filters['sort_order'] ?? 'asc');
        });

        return $query;
    }

    /** Sắp xếp theo cây: Cha đứng trước, các con theo sau. */
    public function scopeTreeOrder($query)
    {
        // Nhóm theo ID (nếu là cha) hoặc parent_id (nếu là con).
        // Cha đứng trước (parent_id is null), con theo sau.
        // Trong nhóm con thì theo sort_order.
        return $query->orderByRaw('COALESCE(parent_id, id) ASC')
            ->orderByRaw('parent_id IS NOT NULL ASC')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');
    }
}
