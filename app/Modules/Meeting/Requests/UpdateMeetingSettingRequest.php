<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingHomeDisplayTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * FE thường gửi lại URL string của ảnh hiện có ("/storage/...") cho image fields khi
     * không upload file mới. Strip ra trước validation — chỉ giữ giá trị nếu là UploadedFile.
     */
    protected function prepareForValidation(): void
    {
        foreach (['projector_image', 'waiting_image', 'chairperson_signature', 'qr_icon'] as $field) {
            if ($this->has($field) && ! $this->file($field) instanceof \Illuminate\Http\UploadedFile) {
                $this->offsetUnset($field);
            }
        }
    }

    public function rules(): array
    {
        return [
            'projector_image'           => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp',
            'waiting_image'             => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp',
            'chairperson_signature'     => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp',
            'qr_icon'                   => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp,svg',
            'remove_projector_image'    => 'sometimes|boolean',
            'remove_waiting_image'      => 'sometimes|boolean',
            'remove_chairperson_signature' => 'sometimes|boolean',
            'remove_qr_icon'            => 'sometimes|boolean',
            'home_display_type'         => ['sometimes', MeetingHomeDisplayTypeEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'image' => ':attribute phải là ảnh hợp lệ.',
            'mimes' => ':attribute phải có định dạng cho phép.',
            'max' => ':attribute không được vượt quá :max KB.',
            'boolean' => ':attribute phải là giá trị đúng/sai.',
        ];
    }

    public function attributes(): array
    {
        return [
            'projector_image'              => 'Hình màn trình chiếu',
            'waiting_image'                => 'Ảnh chờ chương trình',
            'chairperson_signature'        => 'Chữ ký chủ tọa',
            'qr_icon'                      => 'Icon QR code',
            'remove_projector_image'       => 'Xóa hình màn trình chiếu',
            'remove_waiting_image'         => 'Xóa ảnh chờ chương trình',
            'remove_chairperson_signature' => 'Xóa chữ ký chủ tọa',
            'remove_qr_icon'               => 'Xóa icon QR code',
            'allow_host_management'        => 'Cho phép chủ trì điều hành',
            'home_display_type'            => 'Giao diện trang chủ cuộc họp',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'projector_image'       => ['description' => 'Ảnh nền màn trình chiếu (Tab 8). JPG/PNG/WEBP, max 10MB.'],
            'waiting_image'         => ['description' => 'Ảnh chờ chương trình (Tab 8, hiển thị trước khi vào nội dung họp). JPG/PNG/WEBP, max 10MB.'],
            'chairperson_signature' => ['description' => 'Ảnh chữ ký chủ tọa — nhúng vào biên bản .docx. JPG/PNG/WEBP, max 5MB.'],
            'qr_icon'               => ['description' => 'Ảnh icon hiển thị giữa QR code điểm danh. JPG/PNG/WEBP/SVG, max 2MB.'],
            'remove_projector_image'       => ['description' => 'Đặt true để xóa hình hiện tại (không upload mới).', 'example' => false],
            'remove_waiting_image'         => ['description' => 'Đặt true để xóa ảnh chờ chương trình hiện tại.', 'example' => false],
            'remove_chairperson_signature' => ['description' => 'Xóa chữ ký hiện tại.', 'example' => false],
            'remove_qr_icon'               => ['description' => 'Xóa icon QR hiện tại.', 'example' => false],
            'allow_host_management'        => ['description' => 'Tùy chọn chủ trì điều hành cuộc họp.', 'example' => true],
            'home_display_type'            => ['description' => 'Giao diện trang chủ cuộc họp: status_type (theo trạng thái) hoặc meeting_type (theo loại cuộc họp).', 'example' => 'status_type'],
        ];
    }
}
