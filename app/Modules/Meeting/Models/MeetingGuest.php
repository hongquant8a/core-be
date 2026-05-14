<?php

namespace App\Modules\Meeting\Models;

use App\Modules\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Khách mời PER MEETING (KHÔNG phải catalog org-level). Mỗi meeting có list khách
 * mời riêng — admin nhập thông tin trực tiếp ở form tạo/sửa meeting. Khách mời
 * không có user account, chỉ dùng để gửi thư mời (email/SMS) khi meeting publish.
 *
 * Sync: payload Meeting::store/update có field `guests: [{ name, position_name, phone, email, organization_name, id? }]`.
 * Item có `id` → update existing. Không có `id` → tạo mới. Existing không trong list → xóa.
 */
class MeetingGuest extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'meeting_id',
        'name',
        'position_name',
        'phone',
        'email',
        'zalo_user_id',
        'organization_name',
        'invited_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function invitations()
    {
        return $this->hasMany(MeetingInvitation::class, 'meeting_guest_id');
    }
}
