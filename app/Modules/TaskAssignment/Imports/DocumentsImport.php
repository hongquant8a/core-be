<?php

namespace App\Modules\TaskAssignment\Imports;

use App\Modules\Core\Traits\TranslatesExcelHeadings;
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
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Tên văn bản',
        'summary' => 'Tóm tắt',
        'issue_date' => 'Ngày ban hành',
        'type' => 'Loại văn bản',
        'status' => 'Trạng thái',
    ];

    /** Subset xuất ra template — chỉ field required theo StoreDocumentRequest. */
    public const TEMPLATE_LABELS = [
        'name' => 'Tên văn bản',
        'status' => 'Trạng thái',
    ];

    public function model(array $row)
    {
        $typeId = null;
        if (! empty($row['type'])) {
            $typeId = TaskAssignmentType::where('name', $row['type'])->value('id');
        }

        return new TaskAssignmentDocument([
            'name' => $row['name'] ?? null,
            'summary' => $row['summary'] ?? null,
            'issue_date' => $row['issue_date'] ?? null,
            'task_assignment_type_id' => $typeId,
            'status' => $row['status'] ?? 'draft',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['name'] = isset($data['name']) ? (string) $data['name'] : null;
        $data['summary'] = isset($data['summary']) ? (string) $data['summary'] : null;

        return $data;
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
            'name.required' => 'Tên văn bản không được để trống.',
            'name.string' => 'Tên văn bản phải là một chuỗi ký tự.',
            'name.max' => 'Tên văn bản không được vượt quá 255 ký tự.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên văn bản',
            'summary' => 'Tóm tắt',
            'issue_date' => 'Ngày ban hành',
            'task_assignment_type_id' => 'Loại văn bản',
            'status' => 'Trạng thái',
        ];
    }
}
