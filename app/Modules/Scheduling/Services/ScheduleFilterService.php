<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use App\Modules\Scheduling\Enums\SortModeEnum;
use App\Modules\Scheduling\Enums\ViewFilterEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ScheduleFilterService
{
    /**
     * Apply filters to the Schedule query builder.
     */
    public function filter(Builder $query, array $filters): Builder
    {
        $user = auth()->user();
        $userId = $user?->id;

        // Force driver filter if the user is only a driver
        if ($user && $user->hasRole('Lái xe') && !$user->hasAnyRole(['Super Admin', 'Admin', 'Quản trị', 'Tổng hợp lịch', 'Thư ký', 'Lãnh đạo'])) {
            $filters['view'] = 'personal';
            $filters['driver_id'] = $userId;
        }

        // 1. Filter by Module Type (EXECUTIVE, OFFICE)
        if (!empty($filters['module_type'])) {
            $query->where('module_type', $filters['module_type']);
        }

        // 2. Filter by Host ID
        if (!empty($filters['host_id'])) {
            $query->where('host_id', $filters['host_id']);
        }

        // 3. Filter by Driver ID
        if (!empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }

        // 4. Filter by Status
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // 5. Filter by Event Date
        if (!empty($filters['event_date'])) {
            $query->where('event_date', $filters['event_date']);
        }

        // 6. Filter by Date Range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('event_date', [$filters['start_date'], $filters['end_date']]);
        }

        // 7. Filter by Week and Year
        if (isset($filters['week_number'])) {
            $query->where('week_number', $filters['week_number']);
        }
        if (isset($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        // 8. Search Term
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('content', 'like', $search)
                  ->orWhere('location', 'like', $search)
                  ->orWhere('preparation_unit', 'like', $search)
                  ->orWhere('participants_text', 'like', $search)
                  ->orWhere('departments_text', 'like', $search);
            });
        }

        // 9. View Scopes (personal, all, managed)
        $view = $filters['view'] ?? 'all';
        if ($userId) {
            if ($view === 'personal') {
                $groupIds = DB::table('notification_group_members')
                    ->where('user_id', $userId)
                    ->pluck('group_id')
                    ->toArray();

                $query->where(function (Builder $q) use ($userId, $groupIds) {
                    $q->where('created_by', $userId)
                      ->orWhere('host_id', $userId)
                      ->orWhere('driver_id', $userId)
                      ->orWhereHas('recipients', function (Builder $sub) use ($userId, $groupIds) {
                          $sub->where('user_id', $userId);
                          if (!empty($groupIds)) {
                              $sub->orWhereIn('group_id', $groupIds);
                          }
                      });
                });
            } elseif ($view === 'all') {
                // Show published schedules OR schedules created by the current user
                $query->where(function (Builder $q) use ($userId) {
                    $q->where('status', ScheduleStatusEnum::Published)
                      ->orWhere('created_by', $userId);
                });
            }
            // 'managed' view sees everything subject to standard tenant/Spatie policies.
        } else {
            // Unauthenticated callers or system queries without user context see published schedules only
            $query->where('status', ScheduleStatusEnum::Published);
        }

        // 10. Sorting
        if (!empty($filters['sort_by'])) {
            // Client specified custom column sort (e.g. "event_date desc")
            $parts = explode(' ', $filters['sort_by']);
            $column = $parts[0];
            $direction = $parts[1] ?? 'asc';
            $query->orderBy($column, $direction);
        } else {
            $sortMode = $filters['sort_mode'] ?? 'manual';

            switch ($sortMode) {
                case 'time':
                    $query->orderBy('event_date', 'asc')
                          ->orderBy('start_time', 'asc')
                          ->orderBy('host_priority_weight', 'asc');
                    break;
                case 'position':
                    $query->orderBy('host_priority_weight', 'asc')
                          ->orderBy('event_date', 'asc')
                          ->orderBy('start_time', 'asc');
                    break;
                case 'manual':
                default:
                    $query->orderBy('event_date', 'asc')
                          ->orderBy('session', 'asc')
                          ->orderBy('sort_order', 'asc');
                    break;
            }
        }

        return $query;
    }
}
