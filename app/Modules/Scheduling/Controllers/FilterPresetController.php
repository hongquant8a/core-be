<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Scheduling\Models\FilterPreset;
use App\Modules\Scheduling\Requests\StoreFilterPresetRequest;
use App\Modules\Scheduling\Resources\FilterPresetResource;
use App\Modules\Scheduling\Services\FilterPresetService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Scheduling - Bộ lọc cá nhân
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý bộ lọc cá nhân lưu trữ các điều kiện lọc (module_type, status, host_id, v.v.) của người dùng.
 */
class FilterPresetController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private FilterPresetService $presetService) {}

    /**
     * Danh sách bộ lọc cá nhân.
     *
     * @queryParam search string Tìm kiếm theo tên bộ lọc. Example: Lịch quan trọng
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $this->authorize('viewAny', FilterPreset::class);

        $limit = (int) ($request->limit ?? 10);
        $presets = $this->presetService->index($request->all(), $limit);

        return $this->successCollection(FilterPresetResource::collection($presets));
    }

    /**
     * Chi tiết bộ lọc cá nhân.
     *
     * @urlParam filterPreset integer required ID bộ lọc cá nhân. Example: 1
     */
    public function show(FilterPreset $filterPreset)
    {
        $this->authorize('view', $filterPreset);

        $preset = $this->presetService->show($filterPreset);

        return $this->successResource(new FilterPresetResource($preset));
    }

    /**
     * Tạo mới bộ lọc cá nhân.
     *
     * @bodyParam name string required Tên bộ lọc cá nhân. Example: Lịch của tôi tuần này
     * @bodyParam filters object required Mảng chứa các điều kiện lọc. Example: {"view": "personal", "status": 2}
     * @bodyParam is_default boolean Đặt làm bộ lọc mặc định. Example: true
     */
    public function store(StoreFilterPresetRequest $request)
    {
        $this->authorize('create', FilterPreset::class);

        $preset = $this->presetService->store($request->validated());

        return $this->successResource(new FilterPresetResource($preset), 'Tạo bộ lọc cá nhân thành công!', 201);
    }

    /**
     * Cập nhật bộ lọc cá nhân.
     *
     * @urlParam filterPreset integer required ID bộ lọc cá nhân cần sửa. Example: 1
     * @bodyParam name string Tên bộ lọc cá nhân. Example: Lịch của tôi tuần này
     * @bodyParam filters object Mảng chứa các điều kiện lọc. Example: {"view": "personal", "status": 2}
     * @bodyParam is_default boolean Đặt làm bộ lọc mặc định. Example: true
     */
    public function update(StoreFilterPresetRequest $request, FilterPreset $filterPreset)
    {
        $this->authorize('update', $filterPreset);

        $preset = $this->presetService->update($filterPreset, $request->validated());

        return $this->successResource(new FilterPresetResource($preset), 'Cập nhật bộ lọc cá nhân thành công!');
    }

    /**
     * Xóa bộ lọc cá nhân.
     *
     * @urlParam filterPreset integer required ID bộ lọc cá nhân cần xóa. Example: 1
     */
    public function destroy(FilterPreset $filterPreset)
    {
        $this->authorize('delete', $filterPreset);

        $this->presetService->destroy($filterPreset);

        return $this->success(null, 'Xóa bộ lọc cá nhân thành công!');
    }
}
