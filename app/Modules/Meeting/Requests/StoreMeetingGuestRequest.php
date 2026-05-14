<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'note' => 'nullable|string|max:1000',
            'status' => ['nullable', MeetingCatalogStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'email' => ':attribute phải đúng định dạng email.',
            'max' => ':attribute không được vượt quá :max ký tự.',
            'in' => ':attribute không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Họ tên khách mời',
            'email' => 'Email',
            'phone' => 'Số điện thoại',
            'note' => 'Ghi chú',
            'status' => 'Trạng thái',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Họ tên khách mời.', 'example' => 'Nguyễn Văn A'],
            'email' => ['description' => 'Email để gửi thư mời.', 'example' => 'guest@example.com'],
            'phone' => ['description' => 'Số điện thoại để gửi SMS.', 'example' => '0901234567'],
            'note' => ['description' => 'Ghi chú nội bộ.', 'example' => 'Đại diện Sở Y tế'],
            'status' => ['description' => 'Trạng thái danh mục.', 'example' => 'active'],
        ];
    }
}
