<?php

namespace App\Modules\Beneficiary\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Tài liệu hồ sơ — dạng A.
 */
class SaveBeneficiaryDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('document') !== null;

        return [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],

            'files' => ['sometimes', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'],

            // Vắng mặt = request không quản lý tệp → giữ nguyên toàn bộ tệp cũ.
            'sync_attachments' => ['sometimes', 'boolean'],
            'keep_media_ids' => ['sometimes', 'array'],
            'keep_media_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên tài liệu.',
            'name.string' => 'Tên tài liệu phải là chuỗi ký tự.',
            'name.max' => 'Tên tài liệu không được vượt quá :max ký tự.',
            'note.max' => 'Ghi chú không được vượt quá :max ký tự.',
            'files.array' => 'Tệp đính kèm phải là mảng.',
            'files.max' => 'Chỉ được đính kèm tối đa :max tệp.',
            'files.*.file' => 'Tệp đính kèm không hợp lệ.',
            'files.*.mimes' => 'Tệp đính kèm phải thuộc định dạng: pdf, jpg, jpeg, png, webp, doc, docx, xls, xlsx.',
            'files.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'sync_attachments.boolean' => 'Cờ đồng bộ tệp phải là true hoặc false.',
            'keep_media_ids.array' => 'Danh sách tệp giữ lại phải là mảng.',
            'keep_media_ids.*.integer' => 'ID tệp giữ lại phải là số nguyên.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên tài liệu',
            'note' => 'ghi chú',
            'files' => 'tệp đính kèm',
            'sync_attachments' => 'cờ đồng bộ tệp',
            'keep_media_ids' => 'danh sách tệp giữ lại',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Tên tài liệu.', 'example' => 'Quyết định trợ cấp'],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
            'files' => ['description' => 'Tệp đính kèm mới.', 'example' => []],
            'sync_attachments' => ['description' => 'Bật thì tệp không nằm trong keep_media_ids sẽ bị xoá.', 'example' => true],
            'keep_media_ids' => ['description' => 'ID các tệp cũ cần giữ lại.', 'example' => [7, 8]],
        ];
    }
}
