<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Scheduling\Services\OrgSchedulingSettingsService;
use Illuminate\Http\Request;

/**
 * @group Scheduling - Cấu hình duyệt lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Cấu hình quy trình phê duyệt lịch công tác riêng cho từng phân hệ (Thường trực/Văn phòng).
 */
class OrgSchedulingSettingsController extends Controller
{
    public function __construct(private OrgSchedulingSettingsService $settingsService) {}

    /**
     * Lấy cấu hình duyệt lịch công tác hiện tại.
     */
    public function show()
    {
        if (!auth()->user()->hasPermissionTo('scheduling.settings.show')) {
            abort(403, 'Bạn không có quyền xem cấu hình lịch công tác.');
        }

        $settings = $this->settingsService->show();

        return $this->success($settings);
    }

    /**
     * Cập nhật cấu hình duyệt lịch công tác.
     *
     * @bodyParam executive_approval_required boolean required Yêu cầu phê duyệt với phân hệ Thường trực. Example: true
     * @bodyParam office_approval_required boolean required Yêu cầu phê duyệt với phân hệ Văn phòng. Example: false
     * @bodyParam executive_approver_roles string[] required Các role có quyền phê duyệt phân hệ Thường trực. Example: ["Lãnh đạo", "Tổng hợp lịch"]
     * @bodyParam office_approver_roles string[] required Các role có quyền phê duyệt phân hệ Văn phòng. Example: ["Thư ký"]
     */
    public function update(Request $request)
    {
        if (!auth()->user()->hasPermissionTo('scheduling.settings.update')) {
            abort(403, 'Bạn không có quyền cập nhật cấu hình lịch công tác.');
        }

        $validated = $request->validate([
            'executive_approval_required' => 'required|boolean',
            'office_approval_required' => 'required|boolean',
            'executive_approver_roles' => 'required|array',
            'executive_approver_roles.*' => 'string',
            'office_approver_roles' => 'required|array',
            'office_approver_roles.*' => 'string',
        ]);

        $settings = $this->settingsService->update($validated);

        return $this->success($settings, 'Cập nhật cấu hình lịch công tác thành công!');
    }
}
