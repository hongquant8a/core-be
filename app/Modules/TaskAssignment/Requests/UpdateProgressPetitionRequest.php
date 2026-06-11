<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\PetitionStatusEnum;

class UpdateProgressPetitionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'processing_status' => ['nullable', PetitionStatusEnum::rule()],
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
            'in'      => ':attribute không hợp lệ.',
            'date'    => ':attribute phải là ngày hợp lệ.',
            'string'  => ':attribute phải là chuỗi.',
            'array'   => ':attribute phải là mảng.',
            'max'     => ':attribute không được vượt quá :max ký tự/phần tử.',
        ];
    }

    public function attributes(): array
    {
        return [
            'processing_status'      => 'Trạng thái xử lý',
            'completed_at'           => 'Ngày hoàn thành',
            'document_number'        => 'Số ký hiệu văn bản',
            'document_excerpt'       => 'Trích yếu văn bản',
            'response_content'       => 'Tóm tắt nội dung trả lời',
            'attachments'            => 'Đính kèm trả lời',
            'remove_attachment_ids'  => 'DS file đính kèm cần xóa',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'processing_status' => ['description' => 'Trạng thái xử lý mới (new/processing/completed/paused/cancelled).', 'example' => 'completed'],
            'completed_at'     => ['description' => 'Ngày hoàn thành xử lý.', 'example' => '2026-06-14 10:00:00'],
            'document_number'  => ['description' => 'Số ký hiệu văn bản trả lời.', 'example' => '01/UBND-VP'],
            'document_excerpt' => ['description' => 'Trích yếu văn bản.', 'example' => 'V/v giải quyết đơn...'],
            'response_content' => ['description' => 'Tóm tắt nội dung trả lời.', 'example' => 'Đã giải quyết xong...'],
            'attachments'      => ['description' => 'File đính kèm trả lời.', 'example' => []],
            'remove_attachment_ids' => ['description' => 'DS ID attachment cần xóa.', 'example' => [1, 2]],
        ];
    }
}
