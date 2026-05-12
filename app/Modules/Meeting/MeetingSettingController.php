<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Meeting\Requests\UpdateMeetingSettingRequest;
use App\Modules\Meeting\Resources\MeetingSettingResource;
use App\Modules\Meeting\Services\MeetingSettingService;

/**
 * @group Meeting - Cấu hình cuộc họp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Cấu hình singleton mỗi org: hình màn trình chiếu, chữ ký chủ tọa, icon QR.
 * Đổi org -> trả về cấu hình của org mới (auto find-or-create).
 */
class MeetingSettingController extends Controller
{
    public function __construct(private MeetingSettingService $service) {}

    /**
     * Lấy cấu hình cuộc họp của org hiện tại.
     */
    public function show()
    {
        return $this->successResource(new MeetingSettingResource($this->service->show()));
    }

    /**
     * Cập nhật cấu hình (multipart) — gửi 1 hoặc nhiều file. Không gửi field -> giữ nguyên.
     *
     * @bodyParam projector_image file Hình nền màn trình chiếu. JPG/PNG/WEBP, max 10MB.
     * @bodyParam chairperson_signature file Ảnh chữ ký chủ tọa. JPG/PNG/WEBP, max 5MB.
     * @bodyParam qr_icon file Icon center QR. JPG/PNG/WEBP/SVG, max 2MB.
     * @bodyParam remove_projector_image boolean Xóa hình hiện tại. Example: false
     * @bodyParam remove_chairperson_signature boolean Xóa chữ ký hiện tại. Example: false
     * @bodyParam remove_qr_icon boolean Xóa icon QR hiện tại. Example: false
     */
    public function update(UpdateMeetingSettingRequest $request)
    {
        $files = [
            'projector_image' => $request->file('projector_image'),
            'chairperson_signature' => $request->file('chairperson_signature'),
            'qr_icon' => $request->file('qr_icon'),
        ];
        $removeFlags = [
            'projector_image' => (bool) $request->boolean('remove_projector_image'),
            'chairperson_signature' => (bool) $request->boolean('remove_chairperson_signature'),
            'qr_icon' => (bool) $request->boolean('remove_qr_icon'),
        ];

        $item = $this->service->update($files, $removeFlags);

        return $this->successResource(new MeetingSettingResource($item), 'Cập nhật cấu hình cuộc họp thành công!');
    }
}
