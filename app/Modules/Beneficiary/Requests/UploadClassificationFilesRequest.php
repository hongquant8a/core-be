<?php

namespace App\Modules\Beneficiary\Requests;

class UploadClassificationFilesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Vui lòng chọn ít nhất 1 tập tin.',
            'files.array' => 'Danh sách tập tin không hợp lệ.',
            'files.*.file' => 'Tập tin đính kèm không hợp lệ.',
            'files.*.max' => 'Mỗi tập tin không được vượt quá 10MB.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'files' => ['description' => 'Danh sách tập tin quyết định công nhận (nhiều file).', 'example' => null],
        ];
    }

    public function attributes(): array
    {
        return ['files' => 'Tập tin đính kèm'];
    }
}
