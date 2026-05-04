<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingDocumentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_agenda_id' => 'nullable|integer|exists:meeting_agendas,id',
            'meeting_document_type_id' => 'nullable|integer|exists:meeting_document_types,id',
            'title' => 'sometimes|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'remove_attachment_ids' => 'nullable|array',
            'remove_attachment_ids.*' => 'integer|exists:meeting_document_attachments,id',
            'is_public' => 'sometimes|boolean',
            'status' => ['sometimes', MeetingDocumentStatusEnum::rule()],
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
            'meeting_document_type_id' => 'ID loại tài liệu họp',
            'title' => 'Tiêu đề',
            'document_number' => 'document number',
            'summary' => 'summary',
            'attachments' => 'Danh sách tệp đính kèm thêm',
            'attachments.*' => 'Tệp đính kèm',
            'remove_attachment_ids' => 'Danh sách ID tệp cần xóa',
            'is_public' => 'Trạng thái công khai',
            'status' => 'Trạng thái',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Tiêu đề tài liệu.', 'example' => 'Báo cáo cập nhật'],
            'document_number' => ['description' => 'Số hiệu tài liệu.', 'example' => 'BC-02/2026'],
            'summary' => ['description' => 'Trích yếu.', 'example' => 'Tóm tắt nội dung cập nhật'],
            'attachments' => ['description' => 'Mảng tệp đính kèm thêm (multipart/form-data với key `attachments[]`). Mỗi tệp ≤10MB.'],
            'remove_attachment_ids' => ['description' => 'Mảng ID `meeting_document_attachments` cần xóa khỏi document.', 'example' => [12, 13]],
            'is_public' => ['description' => 'Công khai tài liệu.', 'example' => false],
            'status' => ['description' => 'Trạng thái tài liệu.', 'example' => 'published'],
        ];
    }
}
