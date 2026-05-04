<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingDocumentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_id' => 'required|integer|exists:meetings,id',
            'meeting_agenda_id' => 'nullable|integer|exists:meeting_agendas,id',
            'meeting_document_type_id' => 'nullable|integer|exists:meeting_document_types,id',
            'title' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
            'is_public' => 'required|boolean',
            'status' => ['required', MeetingDocumentStatusEnum::rule()],
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
            'meeting_id' => 'ID cuộc họp',
            'meeting_agenda_id' => 'ID chương trình họp',
            'meeting_document_type_id' => 'ID loại tài liệu họp',
            'title' => 'Tiêu đề',
            'document_number' => 'document number',
            'summary' => 'summary',
            'file' => 'Tệp tải lên',
            'is_public' => 'Trạng thái công khai',
            'status' => 'Trạng thái',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_id' => [
                'description' => 'ID cuộc họp.',
                'example' => 1,
            ],
            'meeting_agenda_id' => [
                'description' => 'ID chương trình họp liên quan.',
                'example' => 1,
            ],
            'meeting_document_type_id' => [
                'description' => 'ID loại tài liệu họp.',
                'example' => 1,
            ],
            'title' => [
                'description' => 'Tiêu đề tài liệu.',
                'example' => 'Báo cáo tổng hợp',
            ],
            'document_number' => [
                'description' => 'Số hiệu tài liệu.',
                'example' => 'BC-01/2026',
            ],
            'summary' => [
                'description' => 'Trích yếu nội dung.',
                'example' => 'Nội dung chính của tài liệu.',
            ],
            'file' => [
                'description' => 'Tệp đính kèm tài liệu.',
            ],
            'is_public' => [
                'description' => 'Công khai tài liệu hay không.',
                'example' => true,
            ],
            'status' => [
                'description' => 'Trạng thái tài liệu.',
                'example' => 'draft',
            ],
            'sort_order' => [
                'description' => 'Thứ tự hiển thị.',
                'example' => 1,
            ],
        ];
    }
}
