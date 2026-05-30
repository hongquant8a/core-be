<?php

namespace App\Modules\Scheduling\Models;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FilterPreset extends Model
{
    use HasFactory;

    protected $table = 'filter_presets';

    protected $fillable = [
        'user_id',
        'name',
        'filters',
        'is_default',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'filters' => 'array',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
