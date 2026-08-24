<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tái cấu trúc quan hệ Nhân viên ↔ Phòng ban trong phân hệ Quản lý công việc.
 *
 * Giữ mô hình n-n (3 bảng) nhưng làm lại bảng nối cho đúng:
 *  - Đổi tên `task_assignment_users` → `task_assignment_employee_department`
 *    (khớp quy ước pivot của module, xem `task_assignment_item_user`).
 *  - Khoá là `task_assignment_employee_id` thay cho `user_id` — bảng nối trỏ vào "công dân"
 *    của phân hệ chứ không vào `users` của Core. Cùng khuôn với `meeting_participants`
 *    và `meeting_attendee_group_members` bên phân hệ Phòng họp. Trước đây trỏ `users` nên
 *    có 30/30 dòng mồ côi (thành viên không phải nhân viên của phân hệ).
 *  - Bỏ cột `status`: chỉ ghi lúc tạo, không nơi nào cập nhật, gây ảo giác hai trục
 *    trạng thái. Công tắc duy nhất là `task_assignment_employees.status`.
 *  - Bỏ cột `is_primary`: không có UI để đặt, gán theo thứ tự thao tác tình cờ, và chỉ
 *    phục vụ đúng một chỗ đoán phòng ban khi điều chuyển — nay chỗ đó hỏi thẳng
 *    `to_department_id`. Khái niệm còn lại của bảng nối là `is_representative`.
 *  - Phòng ban chuyển sang ON DELETE RESTRICT (cả bảng nối lẫn bảng phân công công việc)
 *    — trước đây CASCADE nên xoá phòng ban là xoá sạch người được giao của mọi công việc.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Vá dữ liệu: tạo hồ sơ nhân viên cho mọi thành viên đang mồ côi,
        //    nếu không khoá ngoại ghép ở bước 3 sẽ dựng không lên.
        DB::statement("
            INSERT INTO task_assignment_employees (user_id, organization_id, status, created_at, updated_at)
            SELECT DISTINCT tu.user_id, tu.organization_id, 'active', NOW(), NOW()
            FROM task_assignment_users tu
            LEFT JOIN task_assignment_employees e
              ON e.user_id = tu.user_id AND e.organization_id = tu.organization_id
            WHERE e.id IS NULL
        ");

        // 2. Bảng nối mới.
        Schema::create('task_assignment_employee_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_employee_id');
            $table->foreignId('organization_id');
            $table->foreignId('task_assignment_department_id');
            $table->boolean('is_representative')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['task_assignment_employee_id', 'task_assignment_department_id'],
                'ta_emp_dept_emp_dept_unique'
            );
            $table->index(['task_assignment_department_id'], 'ta_emp_dept_dept_index');

            // Đặt tên khoá ngoại thủ công: tên tự sinh của Laravel vượt giới hạn 64 ký tự của MySQL.
            $table->foreign('task_assignment_employee_id', 'ta_emp_dept_employee_foreign')
                ->references('id')->on('task_assignment_employees')
                ->cascadeOnDelete();
            $table->foreign('task_assignment_department_id', 'ta_emp_dept_department_foreign')
                ->references('id')->on('task_assignment_departments')
                ->restrictOnDelete();
        });

        // 3. Chuyển dữ liệu: đổi user_id sang employee_id, bỏ cột status.
        DB::statement('
            INSERT INTO task_assignment_employee_department
                (id, task_assignment_employee_id, organization_id, task_assignment_department_id,
                 is_representative, created_by, updated_by, created_at, updated_at)
            SELECT tu.id, e.id, tu.organization_id, tu.task_assignment_department_id,
                   tu.is_representative, tu.created_by, tu.updated_by, tu.created_at, tu.updated_at
            FROM task_assignment_users tu
            JOIN task_assignment_employees e
              ON e.user_id = tu.user_id AND e.organization_id = tu.organization_id
        ');

        Schema::dropIfExists('task_assignment_users');

        // 4. Phân công công việc: CASCADE → RESTRICT để xoá phòng ban không cuốn theo
        //    người được giao. Guard tầng service báo lỗi thân thiện trước khi chạm DB.
        Schema::table('task_assignment_item_user', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->foreign('department_id')
                ->references('id')->on('task_assignment_departments')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_assignment_item_user', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->foreign('department_id')
                ->references('id')->on('task_assignment_departments')
                ->cascadeOnDelete();
        });

        Schema::create('task_assignment_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_assignment_department_id')
                ->constrained('task_assignment_departments')
                ->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_representative')->default(false);
            $table->string('status', 20)->default('active');
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['user_id', 'task_assignment_department_id', 'organization_id'],
                'ta_users_user_dept_org_unique'
            );
        });

        DB::statement("
            INSERT INTO task_assignment_users
                (id, user_id, organization_id, task_assignment_department_id,
                 is_primary, is_representative, status, created_by, updated_by, created_at, updated_at)
            SELECT m.id, e.user_id, m.organization_id, m.task_assignment_department_id,
                   false, m.is_representative, 'active', m.created_by, m.updated_by, m.created_at, m.updated_at
            FROM task_assignment_employee_department m
            JOIN task_assignment_employees e ON e.id = m.task_assignment_employee_id
        ");

        Schema::dropIfExists('task_assignment_employee_department');
    }
};
