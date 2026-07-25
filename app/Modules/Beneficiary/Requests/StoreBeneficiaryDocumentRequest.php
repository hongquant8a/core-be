<?php

namespace App\Modules\Beneficiary\Requests;

class StoreBeneficiaryDocumentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'beneficiary_id' => 'required|integer|exists:beneficiaries,id',
            'name' => 'required|string|max:255',
            'note' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'file|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'beneficiary_id.required' => 'Người có công không được để trống.',
            'beneficiary_id.exists' => 'Người có công không tồn tại.',
            'name.required' => 'Tên giấy tờ không được để trống.',
            'name.max' => 'Tên giấy tờ không được vượt quá 255 ký tự.',
            'files.array' => 'Danh sách tập tin không hợp lệ.',
            'files.*.file' => 'Tập tin đính kèm không hợp lệ.',
            'files.*.max' => 'Mỗi tập tin không được vượt quá 10MB.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'beneficiary_id' => ['description' => 'ID người có công sở hữu giấy tờ.', 'example' => 1],
            'name' => ['description' => 'Tên giấy tờ.', 'example' => 'Giấy chứng nhận thương binh'],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
            'files' => ['description' => 'Danh sách tập tin đính kèm (nhiều file, mỗi file ≤ 10MB).', 'example' => null],
        ];
    }

    public function attributes(): array
    {
        return [
            'beneficiary_id' => 'Người có công',
            'name' => 'Tên giấy tờ',
            'note' => 'Ghi chú',
            'files' => 'Tập tin đính kèm',
        ];
    }
}
