<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $table = 'notification_templates';

    protected $fillable = [
        'organization_id',
        'module_key',
        'channel',
        'template_id',
        'variable_mapping',
        'is_default',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'variable_mapping' => 'array',
        'is_default' => 'boolean',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function booted()
    {
        static::creating(function (NotificationTemplate $template) {
            $template->created_by = $template->updated_by = auth()->id();
        });

        static::updating(function (NotificationTemplate $template) {
            $template->updated_by = auth()->id();
        });
    }
}
