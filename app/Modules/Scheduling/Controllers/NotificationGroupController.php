<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Models\NotificationGroup;
use App\Modules\Scheduling\Requests\StoreNotificationGroupRequest;
use App\Modules\Scheduling\Requests\UpdateNotificationGroupRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * @group Scheduling - Nhóm nhận thông báo
 * @header X-Organization-Id ID tổ chức cần làm việc. Example: 1
 */
class NotificationGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $orgId = getPermissionsTeamId();
        $groups = NotificationGroup::where('organization_id', $orgId)
            ->with('members')
            ->get();
        return $this->success($groups);
    }

    public function show(NotificationGroup $notificationGroup): JsonResponse
    {
        $this->authorize('view', $notificationGroup);
        return $this->success($notificationGroup->load('members'));
    }

    public function store(StoreNotificationGroupRequest $request): JsonResponse
    {
        $orgId = getPermissionsTeamId();
        $group = DB::transaction(function () use ($request, $orgId) {
            $g = NotificationGroup::create([
                'organization_id' => $orgId,
                'name'            => $request->input('name'),
                'description'     => $request->input('description'),
                'created_by'      => Auth::id() ?? 1,
            ]);

            if ($request->has('members')) {
                $g->members()->sync($request->input('members', []));
            }

            return $g;
        });

        return $this->success($group->load('members'), 'Tạo nhóm nhận thông báo thành công!', 201);
    }

    public function update(UpdateNotificationGroupRequest $request, NotificationGroup $notificationGroup): JsonResponse
    {
        $this->authorize('update', $notificationGroup);
        $group = DB::transaction(function () use ($request, $notificationGroup) {
            $notificationGroup->update($request->only(['name', 'description']));

            if ($request->has('members')) {
                $notificationGroup->members()->sync($request->input('members', []));
            }

            return $notificationGroup;
        });

        return $this->success($group->load('members'), 'Cập nhật nhóm nhận thông báo thành công!');
    }

    public function destroy(NotificationGroup $notificationGroup): JsonResponse
    {
        $this->authorize('delete', $notificationGroup);
        $notificationGroup->delete();
        return $this->success(null, 'Xóa nhóm nhận thông báo thành công!');
    }
}
