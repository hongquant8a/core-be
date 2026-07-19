<?php

namespace App\Modules\TaskAssignment\Requests;

use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;

class BulkUpdateStatusDocumentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:task_assignment_documents,id',
            'status' => ['required', TaskAssignmentDocumentStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID không được để trống.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Danh sách ID phải có ít nhất 1 phần tử.',
            'ids.*.integer' => 'Mỗi ID phải là số nguyên.',
            'ids.*.exists' => 'Văn bản không tồn tại.',
            'status.required' => 'Trạng thái không được để trống.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Danh sách ID văn bản.', 'example' => [1, 2, 3]],
            'status' => ['description' => 'Trạng thái mới.', 'example' => TaskAssignmentDocumentStatusEnum::Draft->value],
        ];
    }

    public function attributes(): array
    {
        return [
            'ids' => 'Danh sách ID',
            'ids.*' => 'ID',
            'status' => 'Trạng thái',
        ];
    }
}
