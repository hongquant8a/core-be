<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Enums\TaskExtensionStatusEnum;

class TaskAssignmentItemExtension extends TenantModel
{
    protected $table = 'task_assignment_item_extensions';

    protected $fillable = [
        'task_assignment_item_id',
        'requested_by_user_id',
        'current_end_at',
        'requested_end_at',
        'reason',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_note',
        'organization_id',
    ];

    protected $casts = [
        'current_end_at' => 'datetime',
        'requested_end_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Hạn gia hạn là trường CHỈ CÓ NGÀY — luôn lưu cuối ngày 23:59:59, cùng quy
     * ước với `TaskAssignmentItem::end_at`. Duyệt xong giá trị này được chép sang
     * `end_at`, nên hai bên phải cùng một giờ. Trước đây web và miniapp tự gắn
     * 17:00:00, khiến việc đã gia hạn bị nhắc theo 17:00 thay vì 23:59:59.
     */
    public function setRequestedEndAtAttribute($value): void
    {
        $this->attributes['requested_end_at'] = TaskAssignmentItem::normalizeDay($value, true);
    }

    public function setCurrentEndAtAttribute($value): void
    {
        $this->attributes['current_end_at'] = TaskAssignmentItem::normalizeDay($value, true);
    }

    /**
     * Công việc được xin gia hạn.
     *
     * Bỏ global scope `issuedDocument`: scope đó lọc chỉ còn công việc thuộc văn
     * bản ĐÃ ban hành. Route binding của item tự bỏ scope (xem
     * TaskAssignmentItem::resolveRouteBinding) nên vẫn tạo được yêu cầu gia hạn
     * cho công việc thuộc văn bản nháp — nhưng quan hệ này thì không, và lúc
     * duyệt sẽ trả `null` rồi nổ khi ghi `end_at`.
     */
    public function item()
    {
        return $this->belongsTo(TaskAssignmentItem::class, 'task_assignment_item_id')
            ->withoutGlobalScope('issuedDocument');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /** Yêu cầu đang chờ duyệt — dùng cho ràng buộc "mỗi công việc chỉ 1 yêu cầu pending". */
    public function scopePending($query)
    {
        return $query->where('status', TaskExtensionStatusEnum::Pending->value);
    }
}
