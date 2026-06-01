<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Requests\StoreSchedulingSettingRequest;
use App\Modules\Scheduling\Resources\SchedulingSettingResource;
use App\Modules\Scheduling\Services\SchedulingSettingService;
use Illuminate\Http\JsonResponse;

/**
 * @group Scheduling - Cấu hình lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý cấu hình chung cho module lịch công tác của tổ chức.
 */
class SchedulingSettingController extends Controller
{
    public function __construct(private SchedulingSettingService $settingService) {}

    /**
     * Lấy thông tin cấu hình lịch công tác của tổ chức.
     */
    public function show(): JsonResponse
    {
        $orgId = getPermissionsTeamId();
        $setting = $this->settingService->get($orgId);
        return $this->successResource(new SchedulingSettingResource($setting));
    }

    /**
     * Cập nhật thông tin cấu hình lịch công tác của tổ chức.
     *
     * @bodyParam approval_enabled boolean required Kích hoạt chế độ duyệt lịch công tác. Example: true
     * @bodyParam approval_module_types array Danh sách phân hệ yêu cầu duyệt lịch. Example: ["EXECUTIVE"]
     * @bodyParam default_channels array Danh sách kênh nhận thông báo mặc định. Example: ["fcm", "inapp"]
     */
    public function update(StoreSchedulingSettingRequest $request): JsonResponse
    {
        $orgId = getPermissionsTeamId();
        $setting = $this->settingService->update($orgId, $request->validated());
        return $this->successResource(new SchedulingSettingResource($setting), 'Cập nhật cấu hình thành công!');
    }
}
