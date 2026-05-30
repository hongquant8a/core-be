<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\FilterPreset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FilterPresetService
{
    /**
     * Get user's personal filter presets.
     */
    public function index(array $filters, int $limit = 10): LengthAwarePaginator
    {
        $userId = auth()->id();

        $query = FilterPreset::where('user_id', $userId);

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('name', 'asc')->paginate($limit);
    }

    /**
     * Get details of a filter preset.
     */
    public function show(FilterPreset $preset): FilterPreset
    {
        return $preset;
    }

    /**
     * Store a new filter preset.
     */
    public function store(array $data): FilterPreset
    {
        $userId = auth()->id();

        // If this is set as default, unset other default presets of this user first
        if (!empty($data['is_default'])) {
            FilterPreset::where('user_id', $userId)->update(['is_default' => false]);
        }

        return FilterPreset::create(array_merge($data, [
            'user_id' => $userId,
        ]));
    }

    /**
     * Update a filter preset.
     */
    public function update(FilterPreset $preset, array $data): FilterPreset
    {
        $userId = auth()->id();

        if (!empty($data['is_default'])) {
            FilterPreset::where('user_id', $userId)
                ->where('id', '!=', $preset->id)
                ->update(['is_default' => false]);
        }

        $preset->update($data);

        return $preset;
    }

    /**
     * Delete a filter preset.
     */
    public function destroy(FilterPreset $preset): bool
    {
        return $preset->delete();
    }
}
