<?php

namespace App\Modules\TaskAssignment\Requests;

class ExportMonthlyReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'month' => 'required|date_format:Y-m',
        ];
    }
}
