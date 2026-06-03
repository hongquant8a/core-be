<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\ReminderPreset;
use Illuminate\Http\JsonResponse;

/**
 * @group Scheduling - Mẫu nhắc lịch
 */
class ReminderPresetController extends Controller
{
    /**
     * Danh sách mẫu nhắc lịch cấu hình sẵn.
     */
    public function index(): JsonResponse
    {
        $orgId = getPermissionsTeamId();
        $presets = ReminderPreset::where('is_active', true)
            ->where(function ($query) use ($orgId) {
                $query->whereNull('organization_id')
                      ->orWhere('organization_id', $orgId);
            })
            ->orderBy('sort_order')
            ->get();

        return $this->success($presets);
    }
}
