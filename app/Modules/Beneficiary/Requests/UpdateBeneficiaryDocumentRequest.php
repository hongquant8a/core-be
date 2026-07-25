<?php

namespace App\Modules\Beneficiary\Requests;

class UpdateBeneficiaryDocumentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'note' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'file|max:10240',
            'files_deleted' => 'nullable|array',
            'files_deleted.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Tên giấy tờ không được vượt quá 255 ký tự.',
            'files.array' => 'Danh sách tập tin không hợp lệ.',
            'files.*.file' => 'Tập tin đính kèm không hợp lệ.',
            'files.*.max' => 'Mỗi tập tin không được vượt quá 10MB.',
            'files_deleted.array' => 'Danh sách ID tập tin cần xóa phải là một mảng.',
            'files_deleted.*.integer' => 'ID tập tin không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Tên giấy tờ.', 'example' => 'Giấy chứng nhận thương binh'],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
            'files' => ['description' => 'Tập tin đính kèm thêm (nhiều file, mỗi file ≤ 10MB).', 'example' => null],
            'files_deleted' => ['description' => 'Danh sách ID tập tin (media) cần xóa.', 'example' => []],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên giấy tờ',
            'note' => 'Ghi chú',
            'files' => 'Tập tin đính kèm',
            'files_deleted' => 'Danh sách tập tin cần xóa',
        ];
    }
}
