<?php

namespace App\Modules\Beneficiary\Models;

use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Đối tượng của người có công — bảng nối dạng D (n–n có thuộc tính).
 *
 * KHÔNG `extends Pivot`: cần khoá chính riêng để spatie gắn media, và cần model event để
 * `$touches` nổ. Xử lý y hệt dạng A (1–n có tệp), cấm dùng `belongsToMany()->sync()`.
 */
class BeneficiaryTypeRelation extends TenantModel implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const MEDIA_COLLECTION = 'beneficiary_type_attachments';

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\BeneficiaryTypeRelationFactory::new();
    }

    protected $table = 'beneficiary_type_relations';

    /**
     * Bump beneficiaries.updated_at mỗi khi dòng con đổi. Đây là cơ chế DUY NHẤT bắt được
     * xung đột giữa màn hình sub-resource và màn hình save-full. KHÔNG ĐƯỢC BỎ.
     */
    protected $touches = ['beneficiary'];

    /**
     * `beneficiary_id` KHÔNG nằm trong fillable — luôn gán qua quan hệ
     * (`$beneficiary->typeRelations()->create(...)`), đó cũng là cơ chế chặn IDOR.
     * `organization_id` cũng không: TenantModel tự gán khi creating.
     */
    protected $fillable = [
        'beneficiary_type_id', 'is_primary',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function beneficiaryType(): BelongsTo
    {
        return $this->belongsTo(BeneficiaryType::class, 'beneficiary_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function registerMediaCollections(): void
    {
        // KHÔNG singleFile() — một loại đối tượng có thể kèm nhiều giấy tờ chứng minh.
        // Disk mặc định (public): quyết định ngày 15/08/2026, không dùng disk private.
        $this->addMediaCollection(self::MEDIA_COLLECTION)
            ->acceptsMimeTypes([
                'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
    }
}
