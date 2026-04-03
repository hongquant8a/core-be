<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\UpdateSettingRequest;
use App\Modules\Core\Services\SettingService;
use Illuminate\Http\Request;

/**
 * @group Core - Setting
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý cấu hình hệ thống: lấy công khai, lấy toàn bộ (auth), cập nhật.
 */
class SettingController extends Controller
{
    public function __construct(private SettingService $settingService) {}

    /**
     * Lấy cấu hình công khai
     *
     * Trả về các key có is_public = true, nhóm theo group. Không cần xác thực.
     *
     * @unauthenticated
     *
     * @response 200 {"success": true, "data": {"general": {"copyright": "© 2026", "language": "vi"}, "social": {...}}}
     */
    public function public(Request $request)
    {
        return $this->success($this->settingService->getPublic());
    }

    /**
     * Lấy toàn bộ cấu hình
     *
     * Trả về tất cả cấu hình nhóm theo group. Yêu cầu xác thực và quyền settings.index.
     *
     * @response 200 {"success": true, "data": {"general": {...}, "admin_page": {...}, "api": {...}, ...}}
     */
    public function index(Request $request)
    {
        return $this->success($this->settingService->getAll());
    }

    /**
     * Lấy một key
     *
     * Nếu key public: không cần auth. Nếu key private: cần auth và quyền settings.show.
     *
     * @urlParam key string required Key cấu hình. Example: copyright
     *
     * @response 200 {"success": true, "data": {"key": "copyright", "value": "© 2026", "group": "general"}}
     */
    public function show(Request $request, string $key)
    {
        $item = $this->settingService->getByKey($key);

        if (! $item) {
            return $this->notFound('Không tìm thấy cấu hình.');
        }

        return $this->success($item);
    }

    /**
     * Cập nhật cấu hình
     *
     * Cập nhật một phần hoặc toàn bộ. Body là object key-value. Chỉ cập nhật các key tồn tại.
     *
     * **Xử lý field ảnh (type=image: icon, logo, admin_background_image, org_select_background_image):**
     * - `multipart/form-data` + file blob → upload ảnh mới, xóa ảnh cũ, trả URL mới.
     * - `application/json` + URL string → giữ nguyên ảnh cũ, không thay đổi.
     * - `application/json` + chuỗi rỗng `""` → xóa ảnh, trả `null`.
     *
     * @bodyParam copyright string optional Thông tin bản quyền. Example: © 2026 QuânDH
     * @bodyParam language string optional Ngôn ngữ. Example: vi
     * @bodyParam logo file optional Ảnh logo (jpg, png, svg, webp, ico, max 5MB).
     * @bodyParam icon file optional Ảnh favicon (jpg, png, svg, webp, ico, max 5MB).
     * @bodyParam log_retention_days integer optional Số ngày giữ nhật ký. Example: 90
     *
     * @response 200 {"success": true, "data": {...}, "message": "Cấu hình đã được cập nhật!"}
     */
    public function update(UpdateSettingRequest $request)
    {
        $data = $this->settingService->update($request->validated());

        return $this->success($data, 'Cấu hình đã được cập nhật!');
    }
}
