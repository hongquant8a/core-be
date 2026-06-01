<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\SchedulingFilterPreset;
use App\Modules\Scheduling\Requests\{StoreSchedulingFilterPresetRequest, UpdateSchedulingFilterPresetRequest};
use App\Modules\Scheduling\Resources\SchedulingFilterPresetResource;
use App\Modules\Scheduling\Services\SchedulingFilterPresetService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @group Scheduling - Bộ lọc cá nhân
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản lý bộ lọc tùy chỉnh cá nhân cho module lịch công tác của người dùng hiện tại.
 */
class SchedulingFilterPresetController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private SchedulingFilterPresetService $presetService) {}

    /**
     * Danh sách bộ lọc cá nhân của người dùng.
     */
    public function index(): JsonResponse
    {
        $orgId  = getPermissionsTeamId();
        $userId = Auth::id();
        $presets = $this->presetService->index($orgId, $userId);
        return $this->successCollection(SchedulingFilterPresetResource::collection($presets));
    }

    /**
     * Tạo mới bộ lọc cá nhân.
     *
     * @bodyParam name string required Tên bộ lọc. Example: Bộ lọc tuần này
     * @bodyParam filters array required Các tham số lọc dạng key-value. Example: {"view":"personal"}
     * @bodyParam is_default boolean Đặt làm bộ lọc mặc định. Example: true
     */
    public function store(StoreSchedulingFilterPresetRequest $request): JsonResponse
    {
        $orgId  = getPermissionsTeamId();
        $userId = Auth::id();
        $preset = $this->presetService->store($orgId, $userId, $request->validated());
        return $this->successResource(new SchedulingFilterPresetResource($preset), 'Tạo bộ lọc thành công!', 201);
    }

    /**
     * Cập nhật thông tin bộ lọc cá nhân.
     *
     * @urlParam schedulingFilterPreset integer required ID bộ lọc cá nhân. Example: 1
     * @bodyParam name string required Tên bộ lọc. Example: Bộ lọc tuần này
     * @bodyParam filters array required Các tham số lọc dạng key-value. Example: {"view":"personal"}
     * @bodyParam is_default boolean Đặt làm bộ lọc mặc định. Example: true
     */
    public function update(UpdateSchedulingFilterPresetRequest $request, SchedulingFilterPreset $schedulingFilterPreset): JsonResponse
    {
        $this->authorize('update', $schedulingFilterPreset);
        $preset = $this->presetService->update($schedulingFilterPreset, $request->validated());
        return $this->successResource(new SchedulingFilterPresetResource($preset), 'Cập nhật bộ lọc thành công!');
    }

    /**
     * Xóa bộ lọc cá nhân.
     *
     * @urlParam schedulingFilterPreset integer required ID bộ lọc cá nhân. Example: 1
     */
    public function destroy(SchedulingFilterPreset $schedulingFilterPreset): JsonResponse
    {
        $this->authorize('delete', $schedulingFilterPreset);
        $this->presetService->destroy($schedulingFilterPreset);
        return $this->success(null, 'Xóa bộ lọc thành công!');
    }
}
