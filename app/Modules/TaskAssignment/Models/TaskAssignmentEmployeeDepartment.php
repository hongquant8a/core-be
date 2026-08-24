<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\TenantModel;

/**
 * Bảng nối n-n giữa nhân viên phân hệ (`task_assignment_employees`) và phòng ban.
 *
 * Khoá là `task_assignment_employee_id`, KHÔNG phải `user_id`: bảng nối trỏ vào "công dân"
 * của phân hệ chứ không vào `users` của Core — cùng khuôn với `meeting_participants` và
 * `meeting_attendee_group_members` bên phân hệ Phòng họp. Nhờ vậy không thể tồn tại
 * membership của người chưa đăng ký nhân viên.
 *
 * Muốn lọc theo người đăng nhập thì dùng scope `forUser()`; muốn lấy user thì đi qua
 * `employee->user`.
 *
 * Không có cột `status`: trạng thái hoạt động nằm ở `task_assignment_employees.status`.
 */
class TaskAssignmentEmployeeDepartment extends TenantModel
{
    protected $table = 'task_assignment_employee_department';

    protected $fillable = [
        'task_assignment_employee_id',
        'task_assignment_department_id',
        'is_representative',
        'organization_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_representative' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function employee()
    {
        return $this->belongsTo(TaskAssignmentEmployee::class, 'task_assignment_employee_id');
    }

    public function department()
    {
        return $this->belongsTo(TaskAssignmentDepartment::class, 'task_assignment_department_id');
    }

    /** Lọc theo user của Core (auth) — một tầng nhảy qua hồ sơ nhân viên. */
    public function scopeForUser($query, int $userId)
    {
        return $query->whereHas('employee', fn ($q) => $q->where('user_id', $userId));
    }

    /**
     * Chỉ lấy membership của nhân viên còn hoạt động.
     *
     * Thay cho `where('status', 'active')` trên chính bảng nối trước đây: cột đó chỉ
     * được ghi lúc tạo và không nơi nào cập nhật, nên vô hiệu hóa nhân viên không có
     * tác dụng. Nay trạng thái chỉ nằm ở `task_assignment_employees.status`.
     */
    public function scopeActiveEmployee($query)
    {
        return $query->whereHas('employee', fn ($q) => $q->where('status', 'active'));
    }
}
