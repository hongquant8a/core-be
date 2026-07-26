<?php

namespace App\Modules\Beneficiary\Models;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Core\Models\TenantModel;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\VietnameseSort;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Beneficiary extends TenantModel
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\BeneficiaryFactory::new();
    }

    protected $table = 'beneficiaries';

    protected $fillable = [
        'household_id', 'residential_area_id', 'full_name', 'date_of_birth', 'birth_year', 'gender', 'id_number',
        'status', 'death_date', 'address', 'latitude',
        'longitude', 'phone', 'note', 'organization_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'death_date' => 'date',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected static function booted()
    {
        static::creating(fn (self $model) => $model->created_by = $model->updated_by = auth()->id());
        static::updating(fn (self $model) => $model->updated_by = auth()->id());
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Tổ dân phố / thôn của chính người có công — độc lập với hộ (hộ có thể để trống, hoặc
     * người có công sinh sống ở địa bàn khác với hộ khẩu).
     */
    public function residentialArea()
    {
        return $this->belongsTo(ResidentialArea::class);
    }

    public function classifications()
    {
        return $this->hasMany(BeneficiaryClassification::class);
    }

    public function primaryClassification()
    {
        return $this->hasOne(BeneficiaryClassification::class)->where('is_primary', true);
    }

    public function dependents()
    {
        return $this->belongsToMany(Dependent::class, 'beneficiary_dependent_relations')
            ->using(BeneficiaryDependentRelation::class)
            ->withPivot(['id', 'relationship_type', 'note'])
            ->withTimestamps();
    }

    public function dependentRelations()
    {
        return $this->hasMany(BeneficiaryDependentRelation::class);
    }

    /** Thân nhân chính — đầu mối liên hệ của hồ sơ (tối đa 1, xem `mapCoordinates()`). */
    public function primaryDependentRelation()
    {
        return $this->hasOne(BeneficiaryDependentRelation::class)->where('is_primary', true);
    }

    /**
     * Tọa độ dùng để định vị hồ sơ trên bản đồ.
     *
     * Người có công ĐÃ MẤT thì lấy theo thân nhân chính — hồ sơ vẫn cần một điểm trên bản đồ để
     * cán bộ đến thăm viếng / chi trả cho thân nhân, trong khi tọa độ của người đã mất không còn
     * ý nghĩa thực địa. Chưa chỉ định thân nhân chính, hoặc thân nhân chính chưa có tọa độ, thì
     * giữ nguyên tọa độ gốc của hồ sơ.
     *
     * Trả kèm `source` để FE nói rõ vì sao một người đã mất lại có vị trí, tránh gây hiểu nhầm.
     * Cần eager load `primaryDependentRelation.dependent`.
     */
    public function mapCoordinates(): array
    {
        $dependent = $this->status === BeneficiaryStatusEnum::Deceased->value
            ? $this->primaryDependentRelation?->dependent
            : null;

        if ($dependent?->latitude !== null && $dependent?->longitude !== null) {
            return [
                'latitude' => $dependent->latitude,
                'longitude' => $dependent->longitude,
                'source' => 'primary_dependent',
            ];
        }

        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'source' => 'self',
        ];
    }

    public function documents()
    {
        return $this->hasMany(BeneficiaryDocument::class);
    }

    /**
     * `search` quét cả THÂN NHÂN (tên, CCCD, SĐT) chứ không riêng người có công — cán bộ tra cứu
     * thường chỉ cầm một mảnh thông tin (một cái tên, một số CCCD) mà không biết nó thuộc về ai.
     *
     * Điều kiện trong `whereHas` phải bọc thêm một lớp closure: Laravel nối ràng buộc tương quan
     * (`beneficiaries.id = pivot.beneficiary_id`) và global scope tenant vào cùng cấp với các
     * `orWhere` bên dưới, không bọc thì AND/OR sai độ ưu tiên và subquery khớp mọi thân nhân.
     */
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('full_name', 'like', '%'.$search.'%')
                ->orWhere('id_number', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')
                ->orWhereHas('dependents', fn ($q3) => $q3->where(fn ($q4) => $q4
                    ->where('beneficiary_dependents.full_name', 'like', '%'.$search.'%')
                    ->orWhere('beneficiary_dependents.id_number', 'like', '%'.$search.'%')
                    ->orWhere('beneficiary_dependents.phone', 'like', '%'.$search.'%')))))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            // 1 người có thể nhiều loại đối tượng — lọc "Thương binh" vẫn ra hồ sơ kiêm nhiều loại.
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->whereHas(
                'classifications', fn ($q2) => $q2->where('type', $type)
            ))
            ->when($filters['household_id'] ?? null, fn ($q, $id) => $q->where('household_id', $id))
            ->when($filters['residential_area_id'] ?? null, fn ($q, $id) => $q->where('residential_area_id', $id))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['sort_by'] ?? 'created_at', function ($q, $sortBy) use ($filters) {
                $allowed = ['id', 'full_name', 'date_of_birth', 'status', 'created_at', 'updated_at'];
                $column = in_array($sortBy, $allowed) ? $sortBy : 'created_at';
                VietnameseSort::apply($q, $column, $filters['sort_order'] ?? 'desc');
            });
    }
}
