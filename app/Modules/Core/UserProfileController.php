<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\User;
use App\Modules\Core\Requests\UpdateUserProfileRequest;
use App\Modules\Core\Resources\UserProfileResource;
use App\Modules\Core\Services\UserProfileService;

/**
 * @group Core - User Profile
 *
 * Quản lý thông tin cá nhân (phone, giới tính, ngày sinh, CCCD, địa chỉ).
 */
class UserProfileController extends Controller
{
    public function __construct(private UserProfileService $service) {}

    /**
     * Lấy profile của user
     *
     * @urlParam user integer required ID người dùng. Example: 1
     *
     * @apiResource App\Modules\Core\Resources\UserProfileResource
     *
     * @apiResourceModel App\Modules\Core\Models\UserProfile
     *
     * @apiResourceAdditional success=true
     */
    public function show(User $user)
    {
        return $this->successResource(new UserProfileResource($this->service->show($user)));
    }

    /**
     * Cập nhật profile của user
     *
     * @urlParam user integer required ID người dùng. Example: 1
     *
     * @bodyParam phone string Số điện thoại. Example: 0901234567
     * @bodyParam gender string Giới tính (male/female/other). Example: male
     * @bodyParam birth_date date Ngày sinh (Y-m-d). Example: 1990-05-15
     * @bodyParam citizen_id string Số CCCD/CMND. Example: 201234567890
     * @bodyParam permanent_address string Địa chỉ thường trú.
     * @bodyParam temporary_address string Địa chỉ tạm trú.
     *
     * @apiResource App\Modules\Core\Resources\UserProfileResource
     *
     * @apiResourceModel App\Modules\Core\Models\UserProfile
     *
     * @apiResourceAdditional success=true message="Cập nhật thông tin cá nhân thành công!"
     */
    public function update(UpdateUserProfileRequest $request, User $user)
    {
        $profile = $this->service->update($user, $request->validated());

        return $this->successResource(new UserProfileResource($profile), 'Cập nhật thông tin cá nhân thành công!');
    }

    /**
     * Lấy profile của tài khoản đang đăng nhập.
     *
     * Auth-only (Sanctum), không yêu cầu Spatie permission.
     */
    public function showMe()
    {
        return $this->successResource(new UserProfileResource($this->service->show(auth()->user())));
    }

    /**
     * Cập nhật profile của tài khoản đang đăng nhập.
     *
     * Auth-only.
     */
    public function updateMe(UpdateUserProfileRequest $request)
    {
        $profile = $this->service->update(auth()->user(), $request->validated());

        return $this->successResource(new UserProfileResource($profile), 'Cập nhật thông tin cá nhân thành công!');
    }
}
