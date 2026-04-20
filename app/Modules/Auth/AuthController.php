<?php

namespace App\Modules\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RequestAccountRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Auth\Requests\SwitchOrganizationRequest;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\CaslAbilityConverter;
use App\Modules\Auth\Services\GuestAccountRequestService;
use App\Modules\Core\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * @group Auth
 *
 * Xác thực: đăng nhập, đăng xuất, quên mật khẩu, đặt lại mật khẩu
 */
class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private GuestAccountRequestService $guestAccountRequestService,
    ) {}

    /**
     * Đăng nhập
     *
     * Trả về access_token, thông tin user và danh sách organization user có thể truy cập.
     * `current_organization_id` lấy từ bảng `user_preferences` nếu còn hợp lệ; nếu user chỉ có đúng một tổ chức thì tự gán và lưu preference; nếu có nhiều tổ chức và chưa có preference hợp lệ thì trả `null` (frontend cần màn chọn tổ chức rồi gọi switch-organization).
     *
     * @unauthenticated
     *
     * @bodyParam email string required Email hoặc tên đăng nhập (user_name). Example: admin@example.com
     * @bodyParam password string required Mật khẩu. Example: password
     *
     * @response 200 {"success": true, "message": "Đăng nhập thành công.", "data": {"access_token": "1|xxx...", "token_type": "Bearer", "user": {"id": 1, "name": "Admin"}, "available_organizations": [{"id": 2, "name": "Sở Nội vụ"}], "current_organization_id": 2, "roles": ["admin"], "permissions": ["users.index", "users.store"], "abilities": [{"action": "index", "subject": "User"}, {"action": "store", "subject": "User"}]}}
     */
    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->email, $request->password);

        if (! $result['ok']) {
            if ($result['type'] === 'unauthorized') {
                return $this->unauthorized($result['message']);
            }

            return $this->forbidden($result['message']);
        }

        return $this->success($result['data'], 'Đăng nhập thành công.');
    }

    /**
     * Lấy thông tin user đăng nhập hiện tại kèm roles và permissions của tổ chức đang chọn.
     *
     * Dùng để Vue Casl khởi tạo ability khi refresh trang. Cần header X-Organization-Id (middleware set.permissions.team đã đặt ngữ cảnh).
     *
     * @response 200 {"success": true, "data": {"user": {"id": 1, "name": "Admin"}, "roles": ["admin"], "permissions": ["users.index", "users.store"], "abilities": [{"action": "index", "subject": "User"}, {"action": "store", "subject": "User"}]}}
     */
    public function me(Request $request)
    {
        $user = $request->user();
        // getAllPermissions() = direct + từ vai trò
        $permissions = $user->getAllPermissions()->pluck('name')->values()->unique()->all();

        return $this->success([
            'user' => (new UserResource($user))->resolve(),
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $permissions,
            'abilities' => CaslAbilityConverter::toCaslAbilities($permissions),
        ]);
    }

    /**
     * Đăng xuất
     *
     * Hủy token hiện tại.
     *
     * @response 200 {"success": true, "message": "Đã đăng xuất"}
     */
    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Đã đăng xuất');
    }

    /**
     * Chuyển tổ chức làm việc
     *
     * Chọn organization để frontend gắn vào header `X-Organization-Id` cho các request tiếp theo. Lưu `current_organization_id` vào bảng `user_preferences` để lần đăng nhập sau tự nhớ (nếu còn hợp lệ).
     *
     * @bodyParam organization_id integer required ID tổ chức muốn chuyển. Example: 2
     *
     * @response 200 {"success": true, "message": "Đã chuyển tổ chức làm việc.", "data": {"current_organization_id": 2, "current_organization": {"id": 2, "name": "Sở Nội vụ"}, "roles": ["admin"], "permissions": ["users.index", "users.store"], "abilities": [{"action": "index", "subject": "User"}, {"action": "store", "subject": "User"}]}}
     */
    public function switchOrganization(SwitchOrganizationRequest $request)
    {
        $result = $this->authService->switchOrganization($request->user(), (int) $request->organization_id);

        if (! $result['ok']) {
            return $this->forbidden($result['message']);
        }

        return $this->success($result['data'], 'Đã chuyển tổ chức làm việc.');
    }

    /**
     * Quên mật khẩu
     *
     * Gửi link đặt lại mật khẩu qua email.
     *
     * @unauthenticated
     *
     * @bodyParam email string required Email tài khoản. Example: user@example.com
     *
     * @response 200 {"success": true, "message": "Link reset đã được gửi vào Email"}
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $ok = $this->authService->forgotPassword($request->email);

        return $ok
            ? $this->success(null, 'Link reset đã được gửi vào Email')
            : $this->error('Không thể gửi mail', 400);
    }

    /**
     * Đặt lại mật khẩu
     *
     * Đặt mật khẩu mới dùng token từ email reset.
     *
     * @unauthenticated
     *
     * @bodyParam email string required Email tài khoản. Example: user@example.com
     * @bodyParam password string required Mật khẩu mới (tối thiểu 6 ký tự, có xác nhận). Example: newpassword123
     * @bodyParam password_confirmation string required Xác nhận mật khẩu.
     * @bodyParam token string required Token từ email reset.
     *
     * @response 200 {"success": true, "message": "Mật khẩu đã được đặt lại"}
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $ok = $this->authService->resetPassword($request->email, $request->password, $request->token);

        return $ok
            ? $this->success(null, 'Mật khẩu đã được đặt lại')
            : $this->error('Không thể đặt lại mật khẩu', 400);
    }

    /**
     * Yêu cầu mở tài khoản (guest)
     *
     * Khách gửi thông tin (Họ tên, SĐT, Email, Nội dung), hệ thống forward qua email tới `contact_email` trong settings.
     * Endpoint giới hạn 5 request / IP / 10 phút.
     *
     * @unauthenticated
     *
     * @response 200 {"success": true, "message": "Yêu cầu đã được gửi."}
     * @response 503 {"success": false, "message": "Hệ thống chưa cấu hình email tiếp nhận. Vui lòng liên hệ quản trị."}
     */
    public function requestAccount(RequestAccountRequest $request)
    {
        $result = $this->guestAccountRequestService->handle($request->validated());

        if (! $result['ok']) {
            return $this->error('Hệ thống chưa cấu hình email tiếp nhận. Vui lòng liên hệ quản trị.', 503);
        }

        return $this->success(null, 'Yêu cầu đã được gửi.');
    }
}
