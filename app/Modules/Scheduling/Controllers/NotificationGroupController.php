<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Scheduling\Models\NotificationGroup;
use App\Modules\Scheduling\Requests\StoreNotificationGroupRequest;
use App\Modules\Scheduling\Resources\NotificationGroupResource;
use App\Modules\Scheduling\Services\NotificationGroupService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Scheduling - Nhóm nhận thông báo
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Danh mục quản lý nhóm người nhận thông báo cấu hình riêng cho từng tổ chức.
 */
class NotificationGroupController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private NotificationGroupService $groupService) {}

    /**
     * Danh sách nhóm nhận thông báo.
     *
     * @queryParam search string Tìm kiếm theo tên nhóm. Example: Nhóm văn phòng
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $this->authorize('viewAny', NotificationGroup::class);

        $limit = (int) ($request->limit ?? 10);
        $groups = $this->groupService->index($request->all(), $limit);

        return $this->successCollection(NotificationGroupResource::collection($groups));
    }

    /**
     * Chi tiết nhóm nhận thông báo.
     *
     * @urlParam notificationGroup integer required ID nhóm nhận thông báo. Example: 1
     */
    public function show(NotificationGroup $notificationGroup)
    {
        $this->authorize('view', $notificationGroup);

        $group = $this->groupService->show($notificationGroup);

        return $this->successResource(new NotificationGroupResource($group));
    }

    /**
     * Tạo mới nhóm nhận thông báo.
     *
     * @bodyParam name string required Tên nhóm nhận thông báo. Example: Ban Giám Đốc
     * @bodyParam description string Mô tả nhóm nhận thông báo. Example: Nhóm nhận thông báo khẩn cấp của các lãnh đạo
     * @bodyParam user_ids integer[] required Danh sách ID người dùng thuộc nhóm. Example: [2, 3, 5]
     */
    public function store(StoreNotificationGroupRequest $request)
    {
        $this->authorize('create', NotificationGroup::class);

        $group = $this->groupService->store($request->validated());

        return $this->successResource(new NotificationGroupResource($group), 'Tạo nhóm nhận thông báo thành công!', 201);
    }

    /**
     * Cập nhật nhóm nhận thông báo.
     *
     * @urlParam notificationGroup integer required ID nhóm nhận thông báo cần sửa. Example: 1
     * @bodyParam name string Tên nhóm nhận thông báo. Example: Ban Giám Đốc
     * @bodyParam description string Mô tả nhóm nhận thông báo. Example: Nhóm nhận thông báo khẩn cấp của các lãnh đạo
     * @bodyParam user_ids integer[] Danh sách ID người dùng thuộc nhóm. Example: [2, 3, 5]
     */
    public function update(StoreNotificationGroupRequest $request, NotificationGroup $notificationGroup)
    {
        $this->authorize('update', $notificationGroup);

        $group = $this->groupService->update($notificationGroup, $request->validated());

        return $this->successResource(new NotificationGroupResource($group), 'Cập nhật nhóm nhận thông báo thành công!');
    }

    /**
     * Xóa nhóm nhận thông báo.
     *
     * @urlParam notificationGroup integer required ID nhóm nhận thông báo cần xóa. Example: 1
     */
    public function destroy(NotificationGroup $notificationGroup)
    {
        $this->authorize('delete', $notificationGroup);

        $this->groupService->destroy($notificationGroup);

        return $this->success(null, 'Xóa nhóm nhận thông báo thành công!');
    }
}
