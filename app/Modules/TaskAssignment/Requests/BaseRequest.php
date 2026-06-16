<?php

namespace App\Modules\TaskAssignment\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function bodyParameters(): array
    {
        return [];
    }

    /**
     * Chuyển đổi departments (mảng department_id + department_role) thành users
     * bằng cách lấy người đại diện (is_representative=true) của từng phòng ban.
     * Gọi trong prepareForValidation() của Store/Update request.
     */
    protected function resolveDepartmentsToUsers(): void
    {
        if (! $this->has('departments')) {
            return;
        }

        // Nếu FE đã gửi users rõ ràng thì bỏ qua departments
        if ($this->has('users')) {
            $this->replace($this->except(['departments']));

            return;
        }

        $orgId = getPermissionsTeamId();
        $departments = $this->input('departments', []);

        if (! is_array($departments) || empty($departments)) {
            return;
        }

        $users = [];
        foreach ($departments as $dept) {
            $deptId = $dept['department_id'] ?? null;
            $deptRole = $dept['department_role'] ?? 'main';
            if (! $deptId) {
                continue;
            }

            $representative = \App\Modules\TaskAssignment\Models\TaskAssignmentUser::where('task_assignment_department_id', $deptId)
                ->where('organization_id', $orgId)
                ->where('is_representative', true)
                ->where('status', 'active')
                ->first();

            if (! $representative) {
                continue; // Sẽ bị bắt bởi rule validate departments.*
            }

            $users[] = [
                'user_id'         => $representative->user_id,
                'department_id'   => (int) $deptId,
                'department_role' => $deptRole,
                'assignment_role' => 'main',
            ];
        }

        $this->merge(['users' => $users]);
    }

    /**
     * Get the validation rule for an individual attachment in the attachments array.
     * This allows both new UploadedFiles and existing file objects/strings (which will be parsed/ignored).
     *
     * @param int $maxSizeKb Maximum file size in kilobytes
     * @param array $mimes Allowed file extensions
     * @return array
     */
    protected function getAttachmentRule(int $maxSizeKb = 20480, array $mimes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif']): array
    {
        return [
            'nullable',
            function ($attribute, $value, $fail) use ($maxSizeKb, $mimes) {
                if ($value instanceof \Illuminate\Http\UploadedFile) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    if (!in_array($extension, $mimes)) {
                        $fail("Tệp đính kèm ({$attribute}) phải có định dạng: " . implode(', ', $mimes) . ".");
                    }
                    if ($value->getSize() > $maxSizeKb * 1024) {
                        $fail("Tệp đính kèm ({$attribute}) không được vượt quá " . ($maxSizeKb / 1024) . "MB.");
                    }
                } elseif (!is_string($value) && !is_array($value)) {
                    $fail("Tệp đính kèm ({$attribute}) không hợp lệ (phải là file mới hoặc thông tin file cũ).");
                }
            },
        ];
    }
}
