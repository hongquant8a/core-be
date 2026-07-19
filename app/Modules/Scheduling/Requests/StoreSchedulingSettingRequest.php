<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSchedulingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_channels'      => ['required', 'array'],
            'default_channels.*'    => ['string', 'in:inapp,fcm,zalo'],
        ];
    }

    public function messages(): array
    {
        return [
            'default_channels.required' => 'Danh sách kênh mặc định không được để trống.',
            'default_channels.array' => 'Danh sách kênh mặc định phải là một mảng.',
            'default_channels.*.string' => 'Kênh mặc định phải là chuỗi ký tự.',
            'default_channels.*.in' => 'Kênh mặc định không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'default_channels' => 'Danh sách kênh mặc định',
            'default_channels.*' => 'Kênh mặc định',
        ];
    }
}
