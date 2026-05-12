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
            'operator_note' => 'sometimes|nullable|string|max:5000',
            'attachment' => 'nullable|file|max:10240',
            'remove_attachment' => 'nullable|boolean',
            'status' => ['sometimes', MeetingDiscussionStatusEnum::rule()],
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
            'operator_note' => 'Ghi chú thảo luận / Nội dung trả lời',
            'attachment' => 'Tệp đính kèm',
            'remove_attachment' => 'Xóa tệp đính kèm',
            'status' => 'Trạng thái',
            'completed_at' => 'Thời điểm hoàn thành',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'type' => ['description' => 'Loại đăng ký.', 'example' => 'question'],
            'content' => ['description' => 'Nội dung đăng ký.', 'example' => 'Xin chất vấn nội dung tài liệu'],
            'operator_note' => [
                'description' => 'Operator/Chair điền sau khi đại biểu xong lượt. Discussion -> ghi chú thảo luận; Question -> nội dung trả lời chất vấn. Đại biểu KHÔNG sửa được field này.',
                'example' => 'Đã trả lời: phân bổ ngân sách bổ sung 200 tỷ VND.',
            ],
            'attachment' => ['description' => 'Tệp đính kèm mới (sẽ thay tệp cũ qua MediaService).'],
            'remove_attachment' => ['description' => 'Xóa tệp đính kèm hiện tại hay không.', 'example' => false],
            'status' => ['description' => 'Trạng thái đăng ký (registered | completed).', 'example' => 'completed'],
            'completed_at' => ['description' => 'Thời điểm hoàn tất.', 'example' => '2026-05-01 09:25:00'],
            'sort_order' => ['description' => 'Thứ tự gọi.', 'example' => 2],
        ];
    }
}
