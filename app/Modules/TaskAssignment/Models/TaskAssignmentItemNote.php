<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\User;
use App\Modules\Core\Traits\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;

class TaskAssignmentItemNote extends Model
{
    use HasOrganizationScope;

    protected $table = 'task_assignment_item_notes';

    /** Append-only: chỉ có created_at, không có updated_at. */
    const UPDATED_AT = null;

    protected $fillable = [
        'task_assignment_item_id',
        'author_user_id',
        'author_role',
        'content',
        'organization_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(TaskAssignmentItem::class, 'task_assignment_item_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
