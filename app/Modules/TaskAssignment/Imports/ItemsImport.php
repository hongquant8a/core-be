<?php

namespace App\Modules\TaskAssignment\Imports;

use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ItemsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures;

    public function __construct(private int $documentId) {}

    public function model(array $row)
    {
        return new TaskAssignmentItem([
            'task_assignment_document_id' => $this->documentId,
            'name' => $row['name'] ?? $row['tên công việc'] ?? null,
            'description' => $row['description'] ?? $row['mô tả'] ?? null,
            'deadline_type' => $row['deadline_type'] ?? $row['loại thời hạn'] ?? 'no_deadline',
            'start_at' => $row['start_at'] ?? $row['bắt đầu'] ?? null,
            'end_at' => $row['end_at'] ?? $row['kết thúc'] ?? null,
            'processing_status' => $row['processing_status'] ?? $row['trạng thái'] ?? 'todo',
            'completion_percent' => $row['completion_percent'] ?? $row['hoàn thành (%)'] ?? 0,
            'priority' => $row['priority'] ?? $row['ưu tiên'] ?? 'medium',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data['name'] = isset($data['name']) ? (string) $data['name'] : null;
        $data['description'] = isset($data['description']) ? (string) $data['description'] : null;

        return $data;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'end_at' => 'required_if:deadline_type,has_deadline',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên công việc không được để trống.',
            'name.string' => 'Tên công việc phải là một chuỗi ký tự.',
            'name.max' => 'Tên công việc không được vượt quá 255 ký tự.',
            'end_at.required_if' => 'Công việc có thời hạn phải có ngày kết thúc.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên công việc',
            'description' => 'Mô tả',
            'deadline_type' => 'Loại thời hạn',
            'start_at' => 'Ngày bắt đầu',
            'end_at' => 'Ngày kết thúc',
            'processing_status' => 'Trạng thái',
            'completion_percent' => 'Hoàn thành (%)',
            'priority' => 'Độ ưu tiên',
        ];
    }
}
