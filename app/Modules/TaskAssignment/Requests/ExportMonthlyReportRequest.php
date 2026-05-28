<?php

namespace App\Modules\TaskAssignment\Requests;

class ExportMonthlyReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'month' => 'nullable|date_format:Y-m',
        ];
    }

    public function attributes(): array
    {
        return [
            'month' => 'Tháng',
        ];
    }
}
