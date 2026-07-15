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
        'phone' => 'Số điện thoại',
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
        'user_name' => 'Tên đăng nhập',
        'phone' => 'Số điện thoại',
        'password' => 'Mật khẩu',
        'status' => 'Trạng thái',
        'organization' => 'Tổ chức',
        'role' => 'Vai trò',
    ];

    public const TEMPLATE_EXAMPLES = [
        'name' => 'Nguyễn Văn A (xóa hàng này)',
        'email' => 'nguyenvana@example.com',
        'user_name' => 'nguyenvana',
        'phone' => '0901234567',
        'password' => 'password',
        'status' => 'active',
        'organization' => 'Tên tổ chức',
        'role' => 'Đại biểu',
    ];

    public function model(array $row)
    {
        $password = trim((string) ($row['password'] ?? ''));

        DB::transaction(function () use ($row, $password) {
            $email = trim($row['email'] ?? '');
            if (!$email) {
                return;
            }

            $userName = trim($row['user_name'] ?? $row['user_name_'] ?? '');
            $phone = trim($row['phone'] ?? '');

            // Kiểm tra trùng lặp tên đăng nhập với tài khoản khác
            if ($userName !== '') {
                $exists = User::where('user_name', $userName)
                    ->where('email', '!=', $email)
                    ->exists();
                if ($exists) {
                    throw new \Exception("Tên đăng nhập '{$userName}' đã được sử dụng bởi người dùng khác.");
                }
            }

            $userData = [
                'name' => trim($row['name'] ?? $row['name_'] ?? ''),
                'status' => trim($row['status'] ?? '') ?: UserStatusEnum::Active->value,
                'updated_by' => auth()->id(),
            ];

            if ($userName !== '') {
                $userData['user_name'] = $userName;
            }

            if ($phone !== '') {
                $userData['phone'] = $phone;
            }

            $user = User::where('email', $email)->first();
            if ($user) {
                // Đối với user đã tồn tại, chỉ cập nhật password nếu được truyền vào tường minh
                if ($password !== '') {
                    $userData['password'] = Hash::make($password);
                }
                $user->update($userData);
            } else {
                // Đối với user mới, gán password mặc định nếu không truyền
                $userData['email'] = $email;
                $userData['password'] = Hash::make($password !== '' ? $password : 'password');
                $userData['user_name'] = $userName ?: null;
                $userData['phone'] = $phone ?: null;

                $user = User::create($userData);
            }

            // Gán tổ chức và vai trò nếu có
            if (!empty($row['organization']) && !empty($row['role'])) {
                $org = Organization::where('name', $row['organization'])->first();
                if (!$org) {
                    throw new \Exception("Tổ chức '{$row['organization']}' không tồn tại.");
                }

                // Tìm vai trò: ưu tiên vai trò riêng của tổ chức, hoặc vai trò chung hệ thống (organization_id is null)
                $role = Role::where('name', $row['role'])
                    ->where(function ($q) use ($org) {
                        $q->where('organization_id', $org->id)
                            ->orWhereNull('organization_id');
                    })
                    ->first();

                if (!$role) {
                    throw new \Exception("Vai trò '{$row['role']}' không hợp lệ hoặc không thuộc tổ chức '{$row['organization']}'.");
                }

                $this->syncAssignment($user, $org->id, $role->id);
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
            ->where('model_type', (new User())->getMorphClass())
            ->where('organization_id', $orgId)
            ->delete();

        DB::table($modelHasRolesTable)->insert([
            'organization_id' => $orgId,
            'role_id' => $roleId,
            'model_type' => (new User())->getMorphClass(),
            'model_id' => $user->id,
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['name'] = isset($data['name']) ? (string) $data['name'] : null;
        $data['user_name'] = isset($data['user_name']) ? (string) $data['user_name'] : null;
        $data['phone'] = isset($data['phone']) ? (string) $data['phone'] : null;
        $data['email'] = isset($data['email']) ? (string) $data['email'] : null;
        $data['password'] = isset($data['password']) ? (string) $data['password'] : null;
        $data['status'] = isset($data['status']) ? (string) $data['status'] : null;
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
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'status' => 'nullable|string|in:'.implode(',', UserStatusEnum::values()),
            'organization' => 'nullable|string|required_with:role|exists:organizations,name',
            'role' => 'nullable|string|required_with:organization|exists:roles,name',
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
            'phone.string' => 'Số điện thoại phải là một chuỗi ký tự.',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'password.string' => 'Mật khẩu phải là một chuỗi ký tự.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ (:input).',
            'organization.exists' => 'Tổ chức :input không tồn tại trên hệ thống.',
            'organization.required_with' => 'Tổ chức không được để trống khi có vai trò.',
            'role.exists' => 'Vai trò :input không tồn tại trên hệ thống.',
            'role.required_with' => 'Vai trò không được để trống khi có tổ chức.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên người dùng',
            'email' => 'Email',
            'user_name' => 'Tên đăng nhập',
            'phone' => 'Số điện thoại',
            'password' => 'Mật khẩu',
            'status' => 'Trạng thái',
            'organization' => 'Tổ chức',
            'role' => 'Vai trò',
        ];
    }
}
