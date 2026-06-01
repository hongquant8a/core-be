<?php

namespace App\Modules\Scheduling\Services;

use App\Modules\Scheduling\Models\SchedulingFilterPreset;
use Illuminate\Support\Facades\DB;

class SchedulingFilterPresetService
{
    public function index(int $orgId, int $userId)
    {
        return SchedulingFilterPreset::where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function store(int $orgId, int $userId, array $data): SchedulingFilterPreset
    {
        return DB::transaction(function () use ($orgId, $userId, $data) {
            if ($data['is_default'] ?? false) {
                // Bỏ default của preset khác trong cùng org+user.
                SchedulingFilterPreset::where('organization_id', $orgId)
                    ->where('user_id', $userId)
                    ->update(['is_default' => false]);
            }
            return SchedulingFilterPreset::create(array_merge($data, [
                'organization_id' => $orgId,
                'user_id'         => $userId,
            ]));
        });
    }

    public function update(SchedulingFilterPreset $preset, array $data): SchedulingFilterPreset
    {
        return DB::transaction(function () use ($preset, $data) {
            if ($data['is_default'] ?? false) {
                SchedulingFilterPreset::where('organization_id', $preset->organization_id)
                    ->where('user_id', $preset->user_id)
                    ->where('id', '!=', $preset->id)
                    ->update(['is_default' => false]);
            }
            $preset->update($data);
            return $preset->fresh();
        });
    }

    public function destroy(SchedulingFilterPreset $preset): void
    {
        $preset->delete();
    }
}
