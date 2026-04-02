<?php

namespace App\Modules\TaskAssignment\Imports;

use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentType;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DocumentsImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures;

    public function model(array $row)
    {
        $typeId = $row['task_assignment_type_id'] ?? null;

        // Hỗ trợ import theo tên loại văn bản
        if (! $typeId && ($typeName = $row['loại văn bản'] ?? $row['type'] ?? null)) {
            $typeId = TaskAssignmentType::where('name', $typeName)->value('id');
        }

        return new TaskAssignmentDocument([
            'name' => $row['name'] ?? $row['tên văn bản'] ?? null,
            'summary' => $row['summary'] ?? $row['tóm tắt'] ?? null,
            'issue_date' => $row['issue_date'] ?? $row['ngày ban hành'] ?? null,
            'task_assignment_type_id' => $typeId,
            'status' => $row['status'] ?? 'draft',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên văn bản là bắt buộc.',
        ];
    }
}
