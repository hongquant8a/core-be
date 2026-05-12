<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'projector_image' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'chairperson_signature' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'qr_icon' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'remove_projector_image' => 'sometimes|boolean',
            'remove_chairperson_signature' => 'sometimes|boolean',
            'remove_qr_icon' => 'sometimes|boolean',
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
            'projector_image' => 'Hình màn trình chiếu',
            'chairperson_signature' => 'Chữ ký chủ tọa',
            'qr_icon' => 'Icon QR code',
            'remove_projector_image' => 'Xóa hình màn trình chiếu',
            'remove_chairperson_signature' => 'Xóa chữ ký chủ tọa',
            'remove_qr_icon' => 'Xóa icon QR code',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'projector_image' => ['description' => 'Ảnh nền màn trình chiếu (Tab 8). JPG/PNG/WEBP, max 10MB.'],
            'chairperson_signature' => ['description' => 'Ảnh chữ ký chủ tọa — nhúng vào biên bản .docx. JPG/PNG/WEBP, max 5MB.'],
            'qr_icon' => ['description' => 'Ảnh icon hiển thị giữa QR code điểm danh. JPG/PNG/WEBP/SVG, max 2MB.'],
            'remove_projector_image' => ['description' => 'Đặt true để xóa hình hiện tại (không upload mới).', 'example' => false],
            'remove_chairperson_signature' => ['description' => 'Xóa chữ ký hiện tại.', 'example' => false],
            'remove_qr_icon' => ['description' => 'Xóa icon QR hiện tại.', 'example' => false],
        ];
    }
}
