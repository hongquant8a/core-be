<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Scheduling\Models\ReminderPreset;
use App\Modules\Scheduling\Requests\StoreReminderPresetRequest;
use App\Modules\Scheduling\Resources\ReminderPresetResource;
use App\Modules\Scheduling\Services\ReminderPresetService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Scheduling - Mốc nhắc lịch mặc định
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Danh mục quản lý mốc thời gian tự động nhắc lịch công tác (ví dụ trước 15 phút, 30 phút, v.v.).
 */
class ReminderPresetController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ReminderPresetService $presetService) {}

    /**
     * Danh sách mốc nhắc lịch.
     *
     * @queryParam search string Tìm kiếm theo tiêu đề mốc nhắc. Example: Trước 30 phút
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $this->authorize('viewAny', ReminderPreset::class);

        $limit = (int) ($request->limit ?? 10);
        $presets = $this->presetService->index($request->all(), $limit);

        return $this->successCollection(ReminderPresetResource::collection($presets));
    }

    /**
     * Chi tiết mốc nhắc lịch.
     *
     * @urlParam reminderPreset integer required ID mốc nhắc lịch. Example: 1
     */
    public function show(ReminderPreset $reminderPreset)
    {
        $this->authorize('view', $reminderPreset);

        $preset = $this->presetService->show($reminderPreset);

        return $this->successResource(new ReminderPresetResource($preset));
    }

    /**
     * Tạo mới mốc nhắc lịch.
     *
     * @bodyParam title string required Tiêu đề hiển thị của mốc nhắc lịch. Example: Trước 30 phút
     * @bodyParam value integer required Giá trị thời gian nhắc lịch (số phút trước khi diễn ra lịch). Example: 30
     */
    public function store(StoreReminderPresetRequest $request)
    {
        $this->authorize('create', ReminderPreset::class);

        $preset = $this->presetService->store($request->validated());

        return $this->successResource(new ReminderPresetResource($preset), 'Tạo mốc nhắc lịch mặc định thành công!', 201);
    }

    /**
     * Cập nhật mốc nhắc lịch.
     *
     * @urlParam reminderPreset integer required ID mốc nhắc lịch cần sửa. Example: 1
     * @bodyParam title string Tiêu đề hiển thị của mốc nhắc lịch. Example: Trước 45 phút
     * @bodyParam value integer Giá trị thời gian nhắc lịch (số phút). Example: 45
     */
    public function update(StoreReminderPresetRequest $request, ReminderPreset $reminderPreset)
    {
        $this->authorize('update', $reminderPreset);

        $preset = $this->presetService->update($reminderPreset, $request->validated());

        return $this->successResource(new ReminderPresetResource($preset), 'Cập nhật mốc nhắc lịch mặc định thành công!');
    }

    /**
     * Xóa mốc nhắc lịch.
     *
     * @urlParam reminderPreset integer required ID mốc nhắc lịch cần xóa. Example: 1
     */
    public function destroy(ReminderPreset $reminderPreset)
    {
        $this->authorize('delete', $reminderPreset);

        $this->presetService->destroy($reminderPreset);

        return $this->success(null, 'Xóa mốc nhắc lịch mặc định thành công!');
    }
}
