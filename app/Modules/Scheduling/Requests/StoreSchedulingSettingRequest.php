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
}
