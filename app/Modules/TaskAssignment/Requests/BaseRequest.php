<?php

namespace App\Modules\TaskAssignment\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function bodyParameters(): array
    {
        return [];
    }

    /**
     * Get the validation rule for an individual attachment in the attachments array.
     * This allows both new UploadedFiles and existing file objects/strings (which will be parsed/ignored).
     *
     * @param int $maxSizeKb Maximum file size in kilobytes
     * @param array $mimes Allowed file extensions
     * @return array
     */
    protected function getAttachmentRule(int $maxSizeKb = 20480, array $mimes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif']): array
    {
        return [
            'nullable',
            function ($attribute, $value, $fail) use ($maxSizeKb, $mimes) {
                if ($value instanceof \Illuminate\Http\UploadedFile) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    if (!in_array($extension, $mimes)) {
                        $fail("Tệp đính kèm ({$attribute}) phải có định dạng: " . implode(', ', $mimes) . ".");
                    }
                    if ($value->getSize() > $maxSizeKb * 1024) {
                        $fail("Tệp đính kèm ({$attribute}) không được vượt quá " . ($maxSizeKb / 1024) . "MB.");
                    }
                } elseif (!is_string($value) && !is_array($value)) {
                    $fail("Tệp đính kèm ({$attribute}) không hợp lệ (phải là file mới hoặc thông tin file cũ).");
                }
            },
        ];
    }
}
