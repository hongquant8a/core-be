<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingDiscussionStatusEnum;
use App\Modules\Meeting\Enums\MeetingDiscussionTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingDiscussionRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_agenda_id' => 'nullable|integer|exists:meeting_agendas,id',
            'type' => ['sometimes', MeetingDiscussionTypeEnum::rule()],
            'content' => 'sometimes|string',
            'file' => 'nullable|file|max:10240',
            'remove_file' => 'nullable|boolean',
            'status' => ['sometimes', MeetingDiscussionStatusEnum::rule()],
            'called_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'sort_order' => 'nullable|integer|min:0',
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
            'meeting_agenda_id' => 'ID chương trình họp',
            'type' => 'type',
            'content' => 'Nội dung',
            'file' => 'Tệp tải lên',
            'remove_file' => 'remove file',
            'status' => 'Trạng thái',
            'called_at' => 'called at',
            'completed_at' => 'completed at',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'type' => ['description' => 'Loại đăng ký.', 'example' => 'question'],
            'content' => ['description' => 'Nội dung đăng ký.', 'example' => 'Xin chất vấn nội dung tài liệu'],
            'file' => ['description' => 'Tệp đính kèm mới.'],
            'remove_file' => ['description' => 'Xóa tệp hiện tại hay không.', 'example' => false],
            'status' => ['description' => 'Trạng thái đăng ký.', 'example' => 'called'],
            'called_at' => ['description' => 'Thời điểm được gọi.', 'example' => '2026-05-01 09:15:00'],
            'completed_at' => ['description' => 'Thời điểm hoàn tất.', 'example' => '2026-05-01 09:25:00'],
            'sort_order' => ['description' => 'Thứ tự gọi.', 'example' => 2],
        ];
    }
}
