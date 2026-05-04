<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingAttendanceStatusEnum;
use App\Modules\Meeting\Enums\MeetingCheckinMethodEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_id' => 'required|integer|exists:meetings,id',
            'meeting_participant_id' => 'required|integer|exists:meeting_participants,id',
            'status' => ['required', MeetingAttendanceStatusEnum::rule()],
            'checkin_method' => ['nullable', MeetingCheckinMethodEnum::rule()],
            'checked_in_at' => 'nullable|date',
            'note' => 'nullable|string',
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
            'meeting_id' => 'ID cuộc họp',
            'meeting_participant_id' => 'ID người tham dự',
            'status' => 'Trạng thái',
            'checkin_method' => 'Phương thức điểm danh',
            'checked_in_at' => 'checked in at',
            'note' => 'Ghi chú',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_id' => ['description' => 'ID cuộc họp.', 'example' => 1],
            'meeting_participant_id' => ['description' => 'ID người tham dự.', 'example' => 1],
            'status' => ['description' => 'Trạng thái điểm danh.', 'example' => 'present'],
            'checkin_method' => ['description' => 'Phương thức điểm danh.', 'example' => 'qr'],
            'checked_in_at' => ['description' => 'Thời điểm điểm danh.', 'example' => '2026-05-01 08:05:00'],
            'note' => ['description' => 'Ghi chú.', 'example' => 'Đến sớm'],
        ];
    }
}
