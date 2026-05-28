<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'in:male,female,other'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'citizen_id' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('user_profiles', 'citizen_id')->ignore($userId, 'user_id'),
            ],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'temporary_address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'gender.in' => 'Giới tính chỉ được là male, female hoặc other.',
            'birth_date.before' => 'Ngày sinh phải trước ngày hiện tại.',
            'citizen_id.unique' => 'Số CCCD/CMND này đã được sử dụng bởi người dùng khác.',
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'Số điện thoại',
            'gender' => 'Gender',
            'birth_date' => 'Ngày sinh',
            'citizen_id' => 'Citizen',
            'permanent_address' => 'Địa chỉ thường trú',
            'temporary_address' => 'Địa chỉ tạm trú',
        ];
    }
}
