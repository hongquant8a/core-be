<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_type_id' => 'nullable|integer|exists:meeting_types,id',
            'meeting_location_id' => 'nullable|integer|exists:meeting_locations,id',
            'title' => 'sometimes|string|max:255',
            'is_public' => 'sometimes|boolean',
            'content' => 'nullable|string',
            'start_time' => 'sometimes|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'status' => ['sometimes', MeetingStatusEnum::rule()],
            'published_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'numeric' => ':attribute phải là số.',
            'boolean' => ':attribute phải là giá trị đúng/sai.',
            'array' => ':attribute phải là mảng.',
            'file' => ':attribute phải là tệp hợp lệ.',
            'mimes' => ':attribute phải đúng định dạng tệp cho phép.',
            'max' => ':attribute không được vượt quá :max ký tự/phần tử/dung lượng.',
            'min' => ':attribute phải lớn hơn hoặc bằng :min.',
            'date' => ':attribute phải là ngày hợp lệ.',
            'after_or_equal' => ':attribute phải sau hoặc bằng :date.',
            'in' => ':attribute không hợp lệ.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
            'unique' => ':attribute đã tồn tại.',
        ];
    }
    public function attributes(): array
    {
        return [
            'meeting_type_id' => 'ID loại cuộc họp',
            'meeting_location_id' => 'ID địa điểm họp',
            'title' => 'Tiêu đề',
            'is_public' => 'Trạng thái công khai',
            'content' => 'Nội dung',
            'start_time' => 'Thời gian bắt đầu',
            'end_time' => 'Thời gian kết thúc',
            'status' => 'Trạng thái',
            'published_at' => 'Thời gian công khai',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_type_id' => [
                'description' => 'ID loại cuộc họp.',
                'example' => 1,
            ],
            'meeting_location_id' => [
                'description' => 'ID địa điểm họp.',
                'example' => 1,
            ],
            'title' => [
                'description' => 'Tên cuộc họp.',
                'example' => 'Họp tổng kết tháng',
            ],
            'is_public' => [
                'description' => 'Công khai cuộc họp hay không.',
                'example' => true,
            ],
            'content' => [
                'description' => 'Nội dung cuộc họp.',
                'example' => 'Cập nhật nội dung họp.',
            ],
            'start_time' => [
                'description' => 'Thời gian bắt đầu.',
                'example' => '2026-05-02 08:00:00',
            ],
            'end_time' => [
                'description' => 'Thời gian kết thúc.',
                'example' => '2026-05-02 11:00:00',
            ],
            'status' => [
                'description' => 'Trạng thái cuộc họp.',
                'example' => 'published',
            ],
            'published_at' => [
                'description' => 'Thời gian ban hành.',
                'example' => '2026-05-01 12:00:00',
            ],
        ];
    }
}
