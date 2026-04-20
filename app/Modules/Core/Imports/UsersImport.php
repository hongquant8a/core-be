<?php

namespace App\Modules\Core\Imports;

use App\Modules\Core\Enums\UserStatusEnum;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class UsersImport implements ToModel, WithHeadingRow, WithValidation
{
    use TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Họ và tên',
        'email' => 'Email',
        'user_name' => 'Tên đăng nhập',
        'password' => 'Mật khẩu',
        'status' => 'Trạng thái',
        'organization' => 'Tổ chức',
        'role' => 'Vai trò',
    ];

    /**
     * Subset xuất ra template.
     * `name`, `email`, `password` là required theo StoreUserRequest.
     * `organization`, `role` dùng name để lookup (Import::model() tìm Organization theo name + Role theo name)
     * — nếu có thì tự động gán user vào org với role tương ứng.
     */
    public const TEMPLATE_LABELS = [
        'name' => 'Họ và tên',
        'email' => 'Email',
        'password' => 'Mật khẩu',
        'organization' => 'Tổ chức',
        'role' => 'Vai trò',
    ];

    public function model(array $row)
    {
        $password = trim((string) ($row['password'] ?? 'password'));

        DB::transaction(function () use ($row, $password) {
            $user = User::updateOrCreate(
                ['email' => trim($row['email'])],
                [
                    'name' => trim($row['name'] ?? $row['name_'] ?? ''),
                    'user_name' => trim($row['user_name'] ?? $row['user_name_'] ?? '') ?: null,
                    'password' => Hash::make($password),
                    'status' => trim($row['status'] ?? '') ?: UserStatusEnum::Active->value,
                    'updated_by' => auth()->id(),
                ]
            );

            // Gán tổ chức và vai trò nếu có
            if (!empty($row['organization']) && !empty($row['role'])) {
                $org = Organization::where('name', $row['organization'])->first();
                if ($org) {
                    // Tìm vai trò: ưu tiên vai trò riêng của tổ chức, hoặc vai trò chung hệ thống (organization_id is null)
                    $role = Role::where('name', $row['role'])
                        ->where(function ($q) use ($org) {
                            $q->where('organization_id', $org->id)
                                ->orWhereNull('organization_id');
                        })
                        ->first();

                    if ($role) {
                        $this->syncAssignment($user, $org->id, $role->id);
                    }
                }
            }
        });

        return null; // Trả về null vì đã lưu thủ công trong transaction để kiểm soát tốt hơn
    }

    protected function syncAssignment(User $user, int $orgId, int $roleId): void
    {
        $tableNames = config('permission.table_names');
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';

        // Xóa các vai trò cũ của user tại tổ chức này (hoặc toàn bộ tùy logic hệ thống)
        // Hiện tại hệ thống cho phép 1 user thuộc nhiều tổ chức, mỗi tổ chức 1 vai trò?
        // Logic UserService::syncUserAssignments xóa toàn bộ rồi thêm lại.
        // Ở đây import 1 dòng, ta chỉ nên cập nhật vai trò tại tổ chức đó để tránh xóa mất các tổ chức khác nếu user đã có.
        
        DB::table($modelHasRolesTable)
            ->where('model_id', $user->id)
            ->where('model_type', User::class)
            ->where('organization_id', $orgId)
            ->delete();

        DB::table($modelHasRolesTable)->insert([
            'organization_id' => $orgId,
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['name'] = isset($data['name']) ? (string) $data['name'] : null;
        $data['user_name'] = isset($data['user_name']) ? (string) $data['user_name'] : null;
        $data['email'] = isset($data['email']) ? (string) $data['email'] : null;
        $data['password'] = isset($data['password']) ? (string) $data['password'] : null;
        $data['organization'] = isset($data['organization']) ? (string) $data['organization'] : null;
        $data['role'] = isset($data['role']) ? (string) $data['role'] : null;

        return $data;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'user_name' => 'nullable|string|max:100|regex:/^[a-zA-Z0-9._-]*$/',
            'password' => 'nullable|string|min:6',
            'organization' => 'nullable|string|exists:organizations,name',
            'role' => 'nullable|string',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên người dùng không được để trống.',
            'name.string' => 'Tên người dùng phải là một chuỗi ký tự.',
            'name.max' => 'Tên người dùng không được vượt quá 255 ký tự.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email :input đã tồn tại trong hệ thống.',
            'user_name.string' => 'Tên đăng nhập phải là một chuỗi ký tự.',
            'user_name.max' => 'Tên đăng nhập không được vượt quá 100 ký tự.',
            'user_name.unique' => 'Tên đăng nhập :input đã tồn tại.',
            'user_name.regex' => 'Tên đăng nhập chỉ chấp nhận chữ, số, dấu chấm, gạch dưới, gạch ngang.',
            'password.string' => 'Mật khẩu phải là một chuỗi ký tự.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'organization.exists' => 'Tổ chức :input không tồn tại trên hệ thống.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên người dùng',
            'email' => 'Email',
            'user_name' => 'Tên đăng nhập',
            'password' => 'Mật khẩu',
            'organization' => 'Tổ chức',
            'role' => 'Vai trò',
        ];
    }
}
