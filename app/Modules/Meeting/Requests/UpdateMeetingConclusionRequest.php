<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingDocumentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingConclusionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
            'remove_file' => 'nullable|boolean',
            'status' => ['sometimes', MeetingDocumentStatusEnum::rule()],
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
            'title' => 'Tiêu đề',
            'content' => 'Nội dung',
            'file' => 'Tệp tải lên',
            'remove_file' => 'remove file',
            'status' => 'Trạng thái',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Tiêu đề kết luận.', 'example' => 'Kết luận phiên họp chiều'],
            'content' => ['description' => 'Nội dung kết luận cập nhật.', 'example' => 'Bổ sung nhiệm vụ thực hiện...'],
            'file' => ['description' => 'Tệp đính kèm mới.'],
            'remove_file' => ['description' => 'Xóa tệp hiện tại hay không.', 'example' => false],
            'status' => ['description' => 'Trạng thái kết luận.', 'example' => 'published'],
        ];
    }
}
