<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\ReminderPreset;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReminderPresetService
{
    /**
     * Get paginated reminder presets.
     */
    public function index(array $filters, int $limit)
    {
        return ReminderPreset::with(['creator'])
            ->filter($filters)
            ->paginate($limit);
    }

    /**
     * Show reminder preset details.
     */
    public function show(ReminderPreset $preset): ReminderPreset
    {
        return $preset->load(['creator']);
    }

    /**
     * Create a new reminder preset.
     */
    public function store(array $validated): ReminderPreset
    {
        // organization_id is auto-assigned via static::creating inside TenantModel / HasOrganizationScope
        return ReminderPreset::create($validated);
    }

    /**
     * Update an existing reminder preset.
     */
    public function update(ReminderPreset $preset, array $validated): ReminderPreset
    {
        $preset->update($validated);
        return $preset;
    }

    /**
     * Delete a reminder preset.
     */
    public function destroy(ReminderPreset $preset): bool
    {
        return $preset->delete();
    }
}
