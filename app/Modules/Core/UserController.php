<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\User;
use App\Modules\Core\Requests\BulkDestroyUserRequest;
use App\Modules\Core\Requests\BulkUpdateStatusUserRequest;
use App\Modules\Core\Requests\ChangeStatusUserRequest;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Requests\ImportUserRequest;
use App\Modules\Core\Requests\StoreUserRequest;
use App\Modules\Core\Requests\UpdateUserRequest;
use App\Modules\Core\Resources\UserCollection;
use App\Modules\Core\Resources\UserResource;
use App\Modules\Core\Services\UserService;

/**
 * @group Core - User
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý người dùng: danh sách, chi tiết, tạo, cập nhật, xóa, thao tác hàng loạt, xuất/nhập Excel, đổi trạng thái.
 */
class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    /**
     * Thống kê người dùng
     *
     * Tổng số, đang kích hoạt (active), không kích hoạt (inactive, banned). Áp dụng cùng bộ lọc với index.
     *
     * @queryParam search string Từ khóa tìm kiếm (name, email, user_name). Example: john
     * @queryParam status string Lọc theo trạng thái: active, inactive, banned.
     * @queryParam organization_id integer Lọc user được gán vai trò trong tổ chức. Example: 28
     * @queryParam sort_by string Sắp xếp theo: id, name, email, user_name, created_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @response 200 {"success": true, "data": {"total": 10, "active": 5, "inactive": 5}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->userService->stats($request->all()));
    }

    /**
     * Phân bố người dùng theo tổ chức (dashboard pie chart)
     *
     * Đếm user theo organization hiện tại (user_preferences.current_organization_id).
     * Trả top N tổ chức + slice "Khác" cho user chưa có tổ chức.
     *
     * @queryParam limit integer Số tổ chức tối đa hiển thị (mặc định 10). Example: 10
     * @queryParam status string Lọc theo trạng thái user: active, inactive, banned.
     * @queryParam search string Tìm kiếm user. Example: john
     *
     * @response 200 {"success":true,"data":[{"organization_id":1,"name":"Sở Nội vụ","total":260},{"organization_id":null,"name":"Khác","total":45}]}
     */
    public function statsByOrganization(FilterRequest $request)
    {
        $limit = (int) $request->input('limit', 10);

        return $this->success($this->userService->statsByOrganization($request->all(), $limit));
    }

    /**
     * Danh sách người dùng
     *
     * Lấy danh sách có phân trang, lọc và sắp xếp.
     *
     * @queryParam search string Từ khóa tìm kiếm (name, email, user_name). Example: john
     * @queryParam status string Lọc theo trạng thái: active, inactive, banned.
     * @queryParam organization_id integer Lọc user được gán vai trò trong tổ chức. Example: 28
     * @queryParam sort_by string Sắp xếp theo: id, name, email, user_name, created_at. Example: created_at
     * @queryParam sort_order string Thứ tự: asc, desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 10
     *
     * @apiResourceCollection App\Modules\Core\Resources\UserCollection
     *
     * @apiResourceModel App\Modules\Core\Models\User paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $users = $this->userService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new UserCollection($users));
    }

    /**
     * Chi tiết người dùng
     *
     * @urlParam user integer required ID người dùng. Example: 1
     *
     * @apiResource App\Modules\Core\Resources\UserResource
     *
     * @apiResourceModel App\Modules\Core\Models\User
     *
     * @apiResourceAdditional success=true
     */
    public function show(User $user)
    {
        return $this->successResource(new UserResource($user->load(['creator.media', 'editor.media', 'profile'])));
    }

    /**
     * Self profile — tài khoản đang đăng nhập.
     *
     * Auth-only (Sanctum), không yêu cầu Spatie permission. Dùng cho trang profile cá nhân.
     */
    public function me()
    {
        $user = auth()->user()->load(['creator.media', 'editor.media', 'profile']);

        return $this->successResource(new UserResource($user));
    }

    /**
     * Cập nhật self profile — tài khoản đang đăng nhập.
     *
     * Auth-only, dùng cùng UpdateUserRequest. Service đảm bảo không cho phép đổi role/quyền (assignments)
     * — non-admin chỉ sửa được name/email/phone/avatar/password/profile basic fields.
     */
    public function updateMe(UpdateUserRequest $request)
    {
        $payload = $request->validated();
        // Self-update tuyệt đối không động vào assignments/status — chặn ở payload trước khi gọi service.
        unset($payload['assignments'], $payload['status']);

        $user = $this->userService->update(auth()->user(), $payload);

        return $this->successResource(new UserResource($user), 'Tài khoản đã được cập nhật!');
    }

    /**
     * Tạo người dùng mới
     *
     * @bodyParam name string required Tên người dùng. Example: Nguyễn Văn A
     * @bodyParam email string required Email (duy nhất). Example: user@example.com
     * @bodyParam password string required Mật khẩu (tối thiểu 6 ký tự). Example: password123
     * @bodyParam password_confirmation string required Xác nhận mật khẩu.
     * @bodyParam status string Trạng thái: active, inactive, banned. Example: active
     * @bodyParam avatar file Ảnh đại diện (jpg, png, svg, webp, max 5MB).
     * @bodyParam assignments array Danh sách gán vai trò theo tổ chức. Ví dụ: [{"role_id":1,"organization_ids":[2,3]},{"role_id":5,"organization_ids":[9]}]
     *
     * **Xử lý avatar:**
     * - `multipart/form-data` + file blob → upload ảnh mới, trả URL.
     * - `application/json` + chuỗi rỗng `""` → xóa ảnh, trả `null`.
     * - Không gửi field avatar → giữ nguyên.
     *
     * @apiResource App\Modules\Core\Resources\UserResource status=201
     *
     * @apiResourceModel App\Modules\Core\Models\User
     *
     * @apiResourceAdditional success=true message="Tài khoản đã được tạo thành công!"
     */
    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->store($request->validated());

        return $this->successResource(new UserResource($user), 'Tài khoản đã được tạo thành công!', 201);
    }

    /**
     * Cập nhật người dùng
     *
     * @urlParam user integer required ID người dùng. Example: 1
     *
     * @bodyParam name string Tên người dùng.
     * @bodyParam email string Email (duy nhất).
     * @bodyParam password string Mật khẩu mới (nếu muốn đổi).
     * @bodyParam password_confirmation string Xác nhận mật khẩu.
     * @bodyParam status string Trạng thái: active, inactive, banned.
     * @bodyParam avatar file Ảnh đại diện (jpg, png, svg, webp, max 5MB).
     * @bodyParam assignments array Danh sách gán vai trò theo tổ chức. Khi gửi field này, hệ thống sẽ đồng bộ lại toàn bộ phân quyền của user.
     *
     * **Xử lý avatar:**
     * - `multipart/form-data` + file blob → upload ảnh mới, xóa ảnh cũ, trả URL mới.
     * - `application/json` + chuỗi rỗng `""` → xóa ảnh, trả `null`.
     * - Không gửi field avatar → giữ nguyên.
     *
     * @apiResource App\Modules\Core\Resources\UserResource
     *
     * @apiResourceModel App\Modules\Core\Models\User
     *
     * @apiResourceAdditional success=true message="Tài khoản đã được cập nhật!"
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->userService->update($user, $request->validated());

        return $this->successResource(new UserResource($user), 'Tài khoản đã được cập nhật!');
    }

    /**
     * Xóa người dùng
     *
     * @urlParam user integer required ID người dùng. Example: 1
     *
     * @response 200 {"success": true, "message": "Tài khoản đã được xóa thành công!"}
     */
    public function destroy(User $user)
    {
        $this->userService->destroy($user);

        return $this->success(null, 'Tài khoản đã được xóa thành công!');
    }

    /**
     * Xóa hàng loạt người dùng
     *
     * @bodyParam ids array required Danh sách ID. Example: [1, 2, 3]
     *
     * @response 200 {"success": true, "message": "Đã xóa thành công các tài khoản được chọn!"}
     */
    public function bulkDestroy(BulkDestroyUserRequest $request)
    {
        $this->userService->bulkDestroy($request->ids);

        return $this->success(null, 'Đã xóa thành công các tài khoản được chọn!');
    }

    /**
     * Cập nhật trạng thái hàng loạt
     *
     * @bodyParam ids array required Danh sách ID. Example: [1, 2, 3]
     * @bodyParam status string required Trạng thái: active, inactive, banned. Example: active
     *
     * @response 200 {"success": true, "message": "Cập nhật trạng thái thành công."}
     */
    public function bulkUpdateStatus(BulkUpdateStatusUserRequest $request)
    {
        $this->userService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái thành công.');
    }

    /**
     * Xuất danh sách người dùng
     *
     * Áp dụng cùng bộ lọc với index. Xuất ra các trường: id, name, email, user_name, status, created_by, updated_by, created_at, updated_at.
     *
     * @queryParam search string Từ khóa tìm kiếm (name, email).
     * @queryParam status string Lọc theo trạng thái: active, inactive, banned.
     * @queryParam organization_id integer Lọc user được gán vai trò trong tổ chức. Example: 28
     * @queryParam sort_by string Sắp xếp theo: id, name, email, created_at.
     * @queryParam sort_order string Thứ tự: asc, desc.
     * @queryParam limit integer Số bản ghi (1-100).
     */
    public function export(FilterRequest $request)
    {
        return $this->userService->export($request->all());
    }

    /**
     * Nhập danh sách người dùng
     *
     * Cột bắt buộc: name, email. Cột không bắt buộc: user_name, password (mặc định "password"), status (mặc định "active").
     *
     * @bodyParam file file required File Excel (xlsx, xls, csv). Cột theo chuẩn export.
     *
     * @response 200 {"success": true, "message": "Import người dùng thành công."}
     */
    public function import(ImportUserRequest $request)
    {
        $this->userService->import($request->file('file'));

        return $this->success(null, 'Import người dùng thành công.');
    }

    /**
     * Tải mẫu import người dùng
     *
     * @response 200 scenario="File Excel mẫu"
     */
    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Core\Exports\ImportTemplateExport(\App\Modules\Core\Imports\UsersImport::TEMPLATE_LABELS, \App\Modules\Core\Imports\UsersImport::TEMPLATE_EXAMPLES),
            'import-users-template.xlsx'
        );
    }

    /**
     * Thay đổi trạng thái người dùng
     *
     * @urlParam user integer required ID người dùng. Example: 1
     *
     * @bodyParam status string required Trạng thái mới: active, inactive, banned. Example: active
     *
     * @apiResource App\Modules\Core\Resources\UserResource
     *
     * @apiResourceModel App\Modules\Core\Models\User
     *
     * @apiResourceAdditional success=true message="Cập nhật trạng thái thành công!"
     */
    public function changeStatus(ChangeStatusUserRequest $request, User $user)
    {
        $user = $this->userService->changeStatus($user, $request->status);

        return $this->successResource(new UserResource($user), 'Cập nhật trạng thái thành công!');
    }
}
