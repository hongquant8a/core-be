<?php

namespace App\Modules\Core\Requests;

use App\Modules\Core\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validKeys = Setting::pluck('type', 'key')->all();
        $rules = [];

        foreach ($this->all() as $key => $value) {
            if (! isset($validKeys[$key])) {
                continue;
            }

            $type = $validKeys[$key];
            $rules[$key] = $this->ruleForType($type, $key);
        }

        return $rules;
    }

    protected function ruleForType(string $type, string $key): array
    {
        if ($type === 'image') {
            $value = $this->file($key) ?? $this->input($key);

            // Nếu là file upload → validate image
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                return ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp,ico'];
            }

            // Nếu là string (URL cũ hoặc rỗng) → accept string
            return ['nullable', 'string'];
        }

        return match ($type) {
            'integer' => ['nullable', 'integer', 'min:0'],
            'boolean' => ['nullable', 'boolean'],
            'json' => ['nullable'],
            default => ['nullable', 'string', 'max:65535'],
        };
    }

    public function messages(): array
    {
        return [
            '*.string' => 'Giá trị phải là chuỗi.',
            '*.integer' => 'Giá trị phải là số nguyên.',
            '*.boolean' => 'Giá trị phải là true hoặc false.',
            '*.array' => 'Giá trị phải là mảng/đối tượng.',
        ];
    }

    /**
     * Mô tả body mẫu cho tài liệu Scribe.
     */
    public function bodyParameters(): array
    {
        return [
            'language' => [
                'description' => 'Ngôn ngữ hệ thống.',
                'example' => 'vi',
            ],
            'copyright' => [
                'description' => 'Thông tin bản quyền hiển thị.',
                'example' => '© 2026 QuânDH',
            ],
            'log_retention_days' => [
                'description' => 'Số ngày lưu log.',
                'example' => 90,
            ],
            'social' => [
                'description' => 'Cấu hình object/json cho nhóm social (nếu có).',
                'example' => ['facebook' => 'https://facebook.com/quandh'],
            ],
        ];
    }
}
