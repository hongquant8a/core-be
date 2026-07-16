<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\ScheduleStatusEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class ChangeStatusVisitScheduleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:done,skipped'],
            'note' => 'nullable|string|max:500',
            'evidence' => 'nullable|array',
        ];
    }

    /**
     * Validate thủ công từng file trong evidence[] (thay vì rule wildcard evidence.*)
     * — Scribe không tạo được ví dụ file hợp lệ cho rule wildcard trên mảng file,
     * gây lỗi "stdClass could not be converted to string" khi scribe:generate build
     * ví dụ bash cho TOÀN BỘ project, không riêng endpoint này.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $evidence = $this->file('evidence', []);

            if (! is_array($evidence)) {
                return;
            }

            foreach ($evidence as $index => $file) {
                if (! $file instanceof UploadedFile) {
                    $validator->errors()->add("evidence.{$index}", 'Ảnh xác nhận không hợp lệ.');

                    continue;
                }
                if (! $file->isValid() || ! in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    $validator->errors()->add("evidence.{$index}", 'Ảnh xác nhận phải là file ảnh (jpg, jpeg, png, gif, webp).');
                }
                if ($file->getSize() > 10240 * 1024) {
                    $validator->errors()->add("evidence.{$index}", 'Ảnh xác nhận không được vượt quá 10MB.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Trạng thái không được để trống.',
            'status.in' => 'Trạng thái chỉ chấp nhận done hoặc skipped.',
            'evidence.array' => 'Ảnh xác nhận phải là một mảng file.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => ['description' => 'done hoặc skipped.', 'example' => ScheduleStatusEnum::Done->value],
            'note' => ['description' => 'Ghi chú (lý do bỏ qua nếu skipped).', 'example' => null],
            'evidence' => ['description' => 'Ảnh xác nhận đã viếng thăm (chỉ áp dụng khi status = done).', 'example' => null],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'Trạng thái', 'note' => 'Ghi chú', 'evidence' => 'Ảnh xác nhận'];
    }
}
