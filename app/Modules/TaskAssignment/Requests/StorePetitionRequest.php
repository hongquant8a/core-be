<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\PetitionStatusEnum;

class StorePetitionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'department_id' => 'required|integer|exists:task_assignment_departments,id',
            'submission_date' => 'required|date',
            'deadline_date' => 'nullable|date|after_or_equal:submission_date',
            'sender_name' => 'required|string|max:255',
            'sender_address' => 'nullable|string|max:500',
            'sender_cccd' => 'nullable|string|max:20',
            'sender_phone' => 'nullable|string|max:30',
            'sender_email' => 'nullable|email|max:255',
            'content' => 'nullable|string',
            'processing_status' => ['nullable', PetitionStatusEnum::rule()],
            'completed_at' => 'nullable|date',
            'document_number' => 'nullable|string|max:255',
            'document_excerpt' => 'nullable|string|max:2000',
            'response_content' => 'nullable|string',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'date' => ':attribute phải là ngày hợp lệ.',
            'after_or_equal' => ':attribute phải sau hoặc bằng :date.',
            'email' => ':attribute phải là email hợp lệ.',
            'array' => ':attribute phải là mảng.',
            'max' => ':attribute không được vượt quá :max ký tự/phần tử.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
            'in' => ':attribute không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'department_id' => 'Phòng ban',
            'submission_date' => 'Ngày gửi đơn',
            'deadline_date' => 'Hạn xử lý',
            'sender_name' => 'Người gửi đơn',
            'sender_address' => 'Địa chỉ',
            'sender_cccd' => 'CCCD',
            'sender_phone' => 'Số điện thoại',
            'sender_email' => 'Email',
            'content' => 'Nội dung đơn',
            'processing_status' => 'Trạng thái',
            'completed_at' => 'Ngày hoàn thành',
            'document_number' => 'Số ký hiệu văn bản',
            'document_excerpt' => 'Trích yếu văn bản',
            'response_content' => 'Tóm tắt nội dung trả lời',
            'attachments' => 'Đính kèm',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'department_id' => ['description' => 'ID phòng ban tiếp nhận.', 'example' => 1],
            'submission_date' => ['description' => 'Ngày gửi đơn.', 'example' => '2026-06-10'],
            'deadline_date' => ['description' => 'Hạn xử lý.', 'example' => '2026-06-15'],
            'sender_name' => ['description' => 'Tên người gửi đơn.', 'example' => 'Nguyễn Văn A'],
            'sender_address' => ['description' => 'Địa chỉ người gửi.', 'example' => 'Số 1, đường A, TP.HCM'],
            'sender_cccd' => ['description' => 'Số CCCD.', 'example' => '079202001234'],
            'sender_phone' => ['description' => 'Số điện thoại.', 'example' => '0901234567'],
            'sender_email' => ['description' => 'Email.', 'example' => 'nguyenvana@example.com'],
            'content' => ['description' => 'Nội dung đơn.', 'example' => 'Nội dung chi tiết...'],
            'attachments' => ['description' => 'File đính kèm.', 'example' => []],
        ];
    }
}
