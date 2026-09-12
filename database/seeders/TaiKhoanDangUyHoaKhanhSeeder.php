<?php

namespace Database\Seeders;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserPreference;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployee;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployeeDepartment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Tài khoản cán bộ Đảng ủy phường Hòa Khánh: đơn vị, tài khoản, nhân viên, vai trò.
 *
 * ⚠️ KHÔNG nằm trong `DatabaseSeeder` — chỉ chạy khi có yêu cầu rõ ràng:
 *
 *     sail artisan db:seed --class=TaiKhoanDangUyHoaKhanhSeeder
 *
 * Vì sao tách ra: đây là dữ liệu của MỘT cơ quan cụ thể, không phải dữ liệu mẫu
 * dùng chung. Máy đã nhập dữ liệu từ hệ thống cũ sẽ có sẵn tài khoản và phòng
 * ban thật; chạy thêm seeder này là chồng thêm 38 tài khoản và 6 đơn vị nữa.
 *
 * Hai username có thể ĐỤNG tài khoản đã có (`thinhp`, `phienmt`): seeder dùng
 * `forceFill()->save()` nên sẽ ghi đè email, điện thoại, trạng thái và ĐẶT LẠI
 * mật khẩu về `123123`. Kiểm tra trước khi chạy trên máy có dữ liệu thật.
 *
 * Chạy SAU PermissionSeeder (cần sẵn 3 vai trò Nhân viên / Trưởng phòng / Lãnh đạo).
 *
 * Quy ước tài khoản:
 *  - `user_name` = tên + họ viết tắt + tên lót viết tắt, bỏ dấu, viết thường.
 *    "Đặng Hồng Quân" → `quandh`; "Đinh Ngô Thị Khánh Luy" → `luydntk`.
 *  - Mật khẩu mặc định `123123`, email `{user_name}@example.com`.
 *
 * Quy ước vai trò (suy từ chức vụ, xem resolveRoleName()):
 *  - Lãnh đạo    : chức vụ cấp cơ quan (Bí thư/Chủ tịch UBND, Phó Chủ tịch HĐND,
 *                  Chủ tịch UBMTTQ) — xem được công việc và đơn thư toàn cơ quan.
 *  - Trưởng phòng: người đứng đầu và cấp phó của đơn vị/ban/đoàn thể.
 *  - Nhân viên   : chuyên viên.
 */
class TaiKhoanDangUyHoaKhanhSeeder extends Seeder
{
    protected const ORG_ID = 1;

    protected const PASSWORD = '123123';

    /** Đơn vị theo thứ tự hiển thị; sort_order xếp sau các phòng ban có sẵn. */
    protected const UNITS = [
        'Văn phòng Đảng ủy',
        'Ban Xây dựng Đảng',
        'Ủy ban kiểm tra',
        'Hội đồng nhân dân',
        'Ủy ban MTTQ Việt Nam',
        'UBND',
    ];

    /**
     * Chức vụ cấp cơ quan → vai trò Lãnh đạo. So khớp nguyên văn chuỗi chức vụ
     * (không dùng str_contains: "Phó Chủ tịch UBMTTQ" không phải "Chủ tịch UBMTTQ").
     */
    protected const LEADER_TITLES = [
        'Phó chủ tịch HĐND',
        'Chủ tịch UBMTTQ',
        "Bí thư Đảng ủy UBND\nChủ tịch UBND",
        "Phó Bí thư Đảng ủy UBND\nPhó Chủ tịch thường trực UBND",
    ];

    /** [họ và tên, số điện thoại, chức vụ, đơn vị]. Người đầu mỗi đơn vị là đại diện. */
    protected const CAN_BO = [
        ['Đặng Minh Trí', '0947296796', 'Chánh văn phòng', 'Văn phòng Đảng ủy'],
        ['Đặng Thị Thanh Hương', '0935790422', 'Phó Chánh văn phòng', 'Văn phòng Đảng ủy'],
        ['Ngô Thị Thương', '0982021003', 'Chuyên viên', 'Văn phòng Đảng ủy'],
        ['Phan Nguyễn Hồng Linh', '0905201816', 'Chuyên viên', 'Văn phòng Đảng ủy'],
        ['Dương Quốc Viên', '0905738683', 'Chuyên viên', 'Văn phòng Đảng ủy'],
        ['Huỳnh Thị Phương Thúy', '0905148331', 'Chuyên viên', 'Văn phòng Đảng ủy'],
        ['Nguyễn Hoàng Anh Tuấn', '0905251515', 'Chuyên viên', 'Văn phòng Đảng ủy'],
        ['Đinh Thị Diễm Châu', '0988373744', 'Trưởng ban', 'Ban Xây dựng Đảng'],
        ['Phạm Văn Lâm', '0983567998', 'Phó Trưởng ban', 'Ban Xây dựng Đảng'],
        ['Mai Thái Phiên', '0935104588', 'Phó Trưởng ban', 'Ban Xây dựng Đảng'],
        ['Nguyễn Văn Hải', '0975755416', 'Chuyên viên', 'Ban Xây dựng Đảng'],
        ['Phạm Thị Minh Phương', '0777333055', 'Chuyên viên', 'Ban Xây dựng Đảng'],
        ['Hồ Thị Ngọc Hương', '0914517242', 'Chuyên viên', 'Ban Xây dựng Đảng'],
        ['Trần Thanh Thắng', '0934813914', 'Chuyên viên', 'Ban Xây dựng Đảng'],
        ['Nguyễn Kim Anh', '0906518135', 'Chuyên viên', 'Ban Xây dựng Đảng'],
        ['Nguyễn Thị Mai Hoa', '0914067055', 'Chuyên viên', 'Ban Xây dựng Đảng'],
        ['Nguyễn Thị Ánh Hằng', '0907716116', 'Chuyên viên', 'Ban Xây dựng Đảng'],
        ['Nguyễn Thị Giang Thủy', '0905909719', 'Chủ nhiệm', 'Ủy ban kiểm tra'],
        ['Phạm Thị Như Hồng', '0905538835', 'Phó Chủ nhiệm', 'Ủy ban kiểm tra'],
        ['Đinh Ngô Thị Khánh Luy', '0905220528', 'Phó Chủ nhiệm', 'Ủy ban kiểm tra'],
        ['Bùi Trung Hiếu', '0901491595', 'Chuyên viên', 'Ủy ban kiểm tra'],
        ['Nguyễn Thị Bích Nguyệt', '0914977786', 'Chuyên viên', 'Ủy ban kiểm tra'],
        ['Nguyễn Thị Kim Cúc', '0905973892', 'Chuyên viên', 'Ủy ban kiểm tra'],
        ['Ngô Tiến Dũng', '0905806505', 'Phó chủ tịch HĐND', 'Hội đồng nhân dân'],
        ['Huỳnh Thanh Bình', '0975007111', 'Chủ tịch UBMTTQ', 'Ủy ban MTTQ Việt Nam'],
        ['Lê Đức Trung', '0905296618', "Phó Chủ tịch UBMTTQ\nChủ tịch hội Cựu Chiến Binh", 'Ủy ban MTTQ Việt Nam'],
        ['Ngô Minh Tuấn', '0935345004', 'Phó Chủ tịch hội Cựu Chiến Binh', 'Ủy ban MTTQ Việt Nam'],
        ['Lê Đoàn Bảo Nguyên', '0935332102', "Phó Chủ tịch UBMTTQ\nBí thư Đoàn Thanh niên", 'Ủy ban MTTQ Việt Nam'],
        ['Nguyễn Thị Thanh Thảo', '0935425187', 'Phó Bí thư Đoàn thanh niên', 'Ủy ban MTTQ Việt Nam'],
        ['Dương Thị Mỹ Vinh', '0905835833', "Phó Chủ tịch UBMTTQ\nChủ tịch hội Liên hiệp phụ nữ", 'Ủy ban MTTQ Việt Nam'],
        ['Phan Thị Mai', '0905741273', 'Phó Chủ tịch hội Liên hiệp phụ nữ', 'Ủy ban MTTQ Việt Nam'],
        ['Phạm Thị Liên', '0905345578', "Phó Chủ tịch UBMTTQ\nChủ tịch hội Nông dân", 'Ủy ban MTTQ Việt Nam'],
        ['Phạm Nguyên Hưng', '0932481168', 'Phó Chủ tịch hội Nông dân', 'Ủy ban MTTQ Việt Nam'],
        ['Trà Thanh Quang', '0399378599', "Phó Chủ tịch UBMTTQ\nChủ tịch Công đoàn", 'Ủy ban MTTQ Việt Nam'],
        ['Hồ Nguyễn Thị Thùy Linh', '0934973643', 'Phó Chủ tịch Công đoàn', 'Ủy ban MTTQ Việt Nam'],
        ['Huỳnh Anh Vũ', '0905234569', "Bí thư Đảng ủy UBND\nChủ tịch UBND", 'UBND'],
        ['Đặng Ngọc Tuấn', '0905001177', "Phó Bí thư Đảng ủy UBND\nPhó Chủ tịch thường trực UBND", 'UBND'],
        ['Phan Thịnh', '0905708351', 'Đảng ủy viên, Chánh văn phòng HĐND & UBND', 'UBND'],
    ];

    /** @var array<string, TaskAssignmentDepartment> tên đơn vị => model */
    protected array $units = [];

    /** @var array<string, Role> tên vai trò => model */
    protected array $roles = [];

    public function run(): void
    {
        setPermissionsTeamId(self::ORG_ID);

        $this->loadRoles();
        $this->seedUnits();
        $this->seedCanBo();

        $this->command?->info('   → '.count($this->units).' đơn vị, '.count(self::CAN_BO).' tài khoản cán bộ cơ quan.');
    }

    protected function loadRoles(): void
    {
        foreach (['Nhân viên', 'Trưởng phòng', 'Lãnh đạo'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();

            if (! $role) {
                $this->command?->warn("   → Không tìm thấy vai trò \"{$name}\" — chạy PermissionSeeder trước, nếu không tài khoản tương ứng sẽ không có quyền.");

                continue;
            }

            $this->roles[$name] = $role;
        }
    }

    protected function seedUnits(): void
    {
        foreach (self::UNITS as $i => $name) {
            $this->units[$name] = TaskAssignmentDepartment::withoutGlobalScopes()->updateOrCreate(
                ['name' => $name, 'organization_id' => self::ORG_ID],
                [
                    'description' => 'Đơn vị thuộc cơ quan.',
                    'status' => StatusEnum::Active->value,
                    'sort_order' => 10 + $i,
                ]
            );
        }
    }

    protected function seedCanBo(): void
    {
        $seenUnits = [];

        foreach (self::CAN_BO as [$hoVaTen, $phone, $chucVu, $donVi]) {
            $userName = self::userName($hoVaTen);
            $roleName = self::resolveRoleName($chucVu);

            $user = $this->upsertUser($userName, $hoVaTen, $phone);

            if ($role = $this->roles[$roleName] ?? null) {
                $user->syncRoles([$role]);
            }

            // Người đầu tiên của mỗi đơn vị làm đại diện — chỉ là gợi ý cho UI khi
            // chọn đơn vị, không liên quan phân quyền.
            $isRepresentative = ! isset($seenUnits[$donVi]);
            $seenUnits[$donVi] = true;

            $this->upsertEmployee($user, $this->units[$donVi], $chucVu, $isRepresentative);
        }
    }

    /** user_name = tên + họ viết tắt + tên lót viết tắt, bỏ dấu, viết thường. */
    public static function userName(string $hoVaTen): string
    {
        $parts = array_map(
            fn (string $part) => Str::lower(Str::ascii($part)),
            preg_split('/\s+/u', trim($hoVaTen))
        );

        $ten = array_pop($parts);

        return $ten.implode('', array_map(fn (string $part) => mb_substr($part, 0, 1), $parts));
    }

    /** Vai trò suy từ chức vụ — xem quy ước ở PHPDoc class. */
    public static function resolveRoleName(string $chucVu): string
    {
        if (in_array($chucVu, self::LEADER_TITLES, true)) {
            return 'Lãnh đạo';
        }

        return $chucVu === 'Chuyên viên' ? 'Nhân viên' : 'Trưởng phòng';
    }

    protected function upsertUser(string $userName, string $name, string $phone): User
    {
        $email = "{$userName}@example.com";

        $user = User::where('user_name', $userName)->orWhere('email', $email)->first();

        $attributes = [
            'name' => $name,
            'email' => $email,
            'user_name' => $userName,
            'phone' => $phone,
            'password' => self::PASSWORD,
            'status' => StatusEnum::Active->value,
            'email_verified_at' => now(),
        ];

        // Đặt lại mật khẩu mỗi lần seed để tài khoản luôn dùng được ngay.
        $user = $user ? tap($user)->forceFill($attributes)->save() : User::create($attributes);

        // Không đặt tổ chức hiện tại thì sau khi đăng nhập user rơi vào màn chọn
        // tổ chức và chưa có vai trò nào.
        UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            ['current_organization_id' => self::ORG_ID]
        );

        return $user;
    }

    protected function upsertEmployee(User $user, TaskAssignmentDepartment $unit, string $chucVu, bool $isRepresentative): TaskAssignmentEmployee
    {
        $employee = TaskAssignmentEmployee::withoutGlobalScopes()->updateOrCreate(
            ['user_id' => $user->id, 'organization_id' => self::ORG_ID],
            ['status' => StatusEnum::Active->value, 'note' => $chucVu]
        );

        TaskAssignmentEmployeeDepartment::withoutGlobalScopes()->updateOrCreate(
            [
                'task_assignment_employee_id' => $employee->id,
                'task_assignment_department_id' => $unit->id,
            ],
            ['organization_id' => self::ORG_ID, 'is_representative' => $isRepresentative]
        );

        return $employee;
    }
}
