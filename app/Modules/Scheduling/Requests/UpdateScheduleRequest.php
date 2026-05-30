<?php

namespace App\Modules\Scheduling\Requests;

class UpdateScheduleRequest extends StoreScheduleRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // Allow partial updates on update request
        foreach ($rules as $field => $rule) {
            if (is_string($rule)) {
                $rules[$field] = 'sometimes|' . $rule;
            } elseif (is_array($rule)) {
                array_unshift($rules[$field], 'sometimes');
            }
        }

        // Add attachment deletion array validation
        $rules['delete_attachments'] = 'nullable|array';
        $rules['delete_attachments.*'] = 'integer|exists:schedule_attachments,id';

        return $rules;
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'delete_attachments' => 'Danh sách tài liệu cần xóa',
        ]);
    }
}
