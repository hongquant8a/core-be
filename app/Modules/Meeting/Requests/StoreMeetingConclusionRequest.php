<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingDocumentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingConclusionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_id' => 'required|integer|exists:meetings,id',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
            'status' => ['required', MeetingDocumentStatusEnum::rule()],
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
            'title' => 'Tiêu đề',
            'content' => 'Nội dung',
            'file' => 'Tệp tải lên',
            'status' => 'Trạng thái',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_id' => ['description' => 'ID cuộc họp.', 'example' => 1],
            'title' => ['description' => 'Tiêu đề kết luận.', 'example' => 'Kết luận phiên họp sáng'],
            'content' => ['description' => 'Nội dung kết luận.', 'example' => 'Thông qua các nội dung chính...'],
            'file' => ['description' => 'Tệp đính kèm kết luận.'],
            'status' => ['description' => 'Trạng thái kết luận.', 'example' => 'draft'],
        ];
    }
}
