<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\PetitionStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployeeDepartment;

class UpdatePetitionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'department_id' => [
                'sometimes',
                'integer',
                'exists:task_assignment_departments,id',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }

                    // Chỉ lập/chuyển đơn thư cho phòng ban mình thuộc về; ai có
                    // `viewAll` thì thao tác được với mọi phòng ban.
                    if (auth()->user()?->can('task-assignment-petitions.viewAll')) {
                        return;
                    }

                    $deptIds = TaskAssignmentEmployeeDepartment::forUser(auth()->id())
                        ->activeEmployee()
                        ->pluck('task_assignment_department_id')
                        ->all();

                    if (in_array((int) $value, array_map('intval', $deptIds), true)) {
                        return;
                    }

                    $fail('Bạn chỉ được lập đơn thư cho phòng ban mình thuộc về.');
                },
            ],
            'submission_date' => 'sometimes|date',
            'deadline_date' => 'nullable|date|after_or_equal:submission_date',
            'sender_name' => 'sometimes|string|max:255',
            'sender_address' => 'nullable|string|max:500',
            'sender_cccd' => 'nullable|string|max:20',
            'sender_phone' => 'nullable|string|max:30',
            'sender_email' => 'nullable|email|max:255',
            'content' => 'nullable|string',
            'processing_status' => ['sometimes', PetitionStatusEnum::rule()],
            'completed_at' => 'nullable|date',
            'document_number' => 'nullable|string|max:255',
            'document_excerpt' => 'nullable|string|max:2000',
            'response_content' => 'nullable|string',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => $this->getAttachmentRule(),
            'remove_attachment_ids' => 'nullable|array',
            'remove_attachment_ids.*' => 'integer',
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
            'remove_attachment_ids' => 'DS file đính kèm cần xóa',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'department_id' => ['description' => 'ID phòng ban tiếp nhận.', 'example' => 1],
            'submission_date' => ['description' => 'Ngày gửi đơn.', 'example' => '2026-06-10'],
            'deadline_date' => ['description' => 'Hạn xử lý.', 'example' => '2026-06-15'],
            'sender_name' => ['description' => 'Tên người gửi đơn.', 'example' => 'Nguyễn Văn A'],
            'sender_address' => ['description' => 'Địa chỉ.', 'example' => 'Số 1, đường A, TP.HCM'],
            'sender_cccd' => ['description' => 'Số CCCD.', 'example' => '079202001234'],
            'sender_phone' => ['description' => 'Số điện thoại.', 'example' => '0901234567'],
            'sender_email' => ['description' => 'Email.', 'example' => 'nguyenvana@example.com'],
            'content' => ['description' => 'Nội dung đơn.', 'example' => 'Nội dung chi tiết...'],
            'processing_status' => ['description' => 'Trạng thái xử lý.', 'example' => 'processing'],
            'completed_at' => ['description' => 'Ngày hoàn thành xử lý.', 'example' => '2026-06-14 10:00:00'],
            'document_number' => ['description' => 'Số ký hiệu văn bản trả lời.', 'example' => '01/UBND-VP'],
            'document_excerpt' => ['description' => 'Trích yếu văn bản.', 'example' => 'V/v giải quyết đơn...'],
            'response_content' => ['description' => 'Tóm tắt nội dung trả lời.', 'example' => 'Đã giải quyết xong...'],
            'attachments' => ['description' => 'File đính kèm trả lời.', 'example' => []],
            'remove_attachment_ids' => ['description' => 'DS ID attachment cần xóa.', 'example' => [1, 2]],
        ];
    }
}
