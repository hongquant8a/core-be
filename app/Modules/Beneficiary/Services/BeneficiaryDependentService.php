<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryDependent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Thân nhân — dạng B (1–n không tệp).
 */
class BeneficiaryDependentService
{
    private const FILLABLE = [
        'full_name', 'birth_date', 'birth_year', 'gender', 'id_number', 'phone',
        'residential_area_id', 'address', 'latitude', 'longitude', 'note',
        'relationship_id', 'is_primary',
    ];

    private const WITH = [
        'beneficiary', 'residentialArea', 'relationship', 'creator.media', 'editor.media',
    ];

    public function index(Beneficiary $beneficiary, array $filters = [], int $limit = 10): LengthAwarePaginator
    {
        return $beneficiary->dependents()
            ->with(self::WITH)
            ->when($filters['search'] ?? null, fn ($q, $kw) => $q->where(
                fn ($sub) => $sub->where('full_name', 'like', "%{$kw}%")
                    ->orWhere('id_number', 'like', "%{$kw}%")
                    ->orWhere('phone', 'like', "%{$kw}%")
            ))
            ->when($filters['relationship_id'] ?? null, fn ($q, $id) => $q->where('relationship_id', $id))
            ->when($filters['residential_area_id'] ?? null, fn ($q, $id) => $q->where('residential_area_id', $id))
            ->when($filters['gender'] ?? null, fn ($q, $g) => $q->where('gender', $g))
            ->when(
                array_key_exists('is_primary', $filters) && $filters['is_primary'] !== null,
                fn ($q) => $q->where('is_primary', filter_var($filters['is_primary'], FILTER_VALIDATE_BOOLEAN))
            )
            ->orderByDesc('is_primary')
            ->orderBy($filters['sort_by'] ?? 'id', $filters['sort_order'] ?? 'desc')
            ->paginate($limit);
    }

    public function show(BeneficiaryDependent $dependent): BeneficiaryDependent
    {
        return $dependent->load(self::WITH);
    }

    public function store(Beneficiary $beneficiary, array $data): BeneficiaryDependent
    {
        $dependent = DB::transaction(function () use ($beneficiary, $data) {
            $item = $beneficiary->dependents()->create(Arr::only($data, self::FILLABLE));
            $this->demoteOtherPrimaries($beneficiary, $item);

            return $item;
        });

        return $dependent->load(self::WITH);
    }

    public function update(BeneficiaryDependent $dependent, array $data): BeneficiaryDependent
    {
        DB::transaction(function () use ($dependent, $data) {
            $dependent->update(Arr::only($data, self::FILLABLE));
            $this->demoteOtherPrimaries($dependent->beneficiary, $dependent);
        });

        return $dependent->load(self::WITH);
    }

    public function destroy(BeneficiaryDependent $dependent): void
    {
        $dependent->delete();
    }

    /** Chạy qua quan hệ nên không đụng được dòng của hồ sơ khác. */
    public function bulkDestroy(Beneficiary $beneficiary, array $ids): int
    {
        $deleted = $beneficiary->dependents()->whereIn('id', $ids)->delete();

        // Query Builder không kích hoạt $touches — phải touch tay.
        $beneficiary->touch();

        return $deleted;
    }

    private function demoteOtherPrimaries(Beneficiary $beneficiary, BeneficiaryDependent $primary): void
    {
        if (! $primary->is_primary) {
            return;
        }

        $beneficiary->dependents()
            ->whereKeyNot($primary->getKey())
            ->where('is_primary', true)
            ->update(['is_primary' => false, 'updated_by' => auth()->id(), 'updated_at' => now()]);
    }
}
