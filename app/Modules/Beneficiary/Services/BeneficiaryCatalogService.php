<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use App\Modules\Beneficiary\Exceptions\CatalogInUseException;
use App\Modules\Beneficiary\Models\BeneficiaryRelationship;
use App\Modules\Beneficiary\Models\BeneficiaryResidentialArea;
use App\Modules\Beneficiary\Models\BeneficiaryType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Service dùng chung cho ba bảng danh mục — cùng tập cột (`name`, `note`, `sort_order`,
 * `status`) và cùng bộ hành vi, chỉ khác model và quan hệ dùng để đếm tham chiếu.
 *
 * Tham số hoá theo `$modelClass` thay vì viết ba service giống hệt nhau, theo đúng tiền lệ
 * `App\Modules\Meeting\Services\CatalogService`.
 */
class BeneficiaryCatalogService
{
    private const FILLABLE = ['name', 'note', 'sort_order', 'status'];

    /**
     * Quan hệ dùng để đếm số bản ghi đang tham chiếu tới một mục danh mục.
     *
     * Dùng cho hai việc: trả `usage_count` cho FE, và giải thích vì sao xoá bị chặn.
     * Tổ dân phố bị tham chiếu từ HAI bảng nên phải cộng cả hai.
     */
    private const USAGE_RELATIONS = [
        BeneficiaryResidentialArea::class => ['beneficiaries', 'dependents'],
        BeneficiaryType::class => ['typeRelations'],
        BeneficiaryRelationship::class => ['dependents'],
    ];

    public function stats(string $modelClass, array $filters = []): array
    {
        $base = $modelClass::query()
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', CatalogStatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', CatalogStatusEnum::Inactive->value)->count(),
        ];
    }

    /**
     * Mặc định sắp xếp theo `sort_order` rồi `name` — thứ tự cán bộ tự đặt cho dropdown.
     *
     * KHÔNG tự lọc `status = active`: màn quản trị danh mục cần thấy cả dòng đã ngừng dùng.
     * FE dựng dropdown phải truyền `status=active` tường minh.
     */
    public function index(string $modelClass, array $filters = [], int $limit = 10): LengthAwarePaginator
    {
        $sortBy = $filters['sort_by'] ?? null;

        $query = $modelClass::query()
            // creator.media/editor.media (không phải creator/editor): FormatsUserSummary gọi
            // getFirstMedia('avatars') nên thiếu .media là N+1. Không nạp thì Resource trả
            // created_by/updated_by = null vì dùng whenLoaded() — cùng lý do với show().
            ->with(['creator.media', 'editor.media'])
            ->withCount(self::USAGE_RELATIONS[$modelClass])
            ->when($filters['search'] ?? null, fn ($q, $kw) => $q->where('name', 'like', "%{$kw}%"))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        $sortBy
            ? $query->orderBy($sortBy, $filters['sort_order'] ?? 'asc')
            : $query->orderBy('sort_order')->orderBy('name');

        return $query->paginate($limit);
    }

    public function show(Model $catalog): Model
    {
        return $catalog->load(['creator.media', 'editor.media'])
            ->loadCount(self::USAGE_RELATIONS[$catalog::class]);
    }

    /**
     * UNIQUE(organization_id, name) + SoftDeletes: nhập lại đúng tên một mục đã xoá phải là
     * khôi phục mục đó, không phải nhận SQLSTATE 23000. Đây là hệ quả trực tiếp của việc bỏ
     * cột `code` — `name` trở thành định danh duy nhất.
     */
    public function store(string $modelClass, array $data): Model
    {
        $attributes = Arr::only($data, self::FILLABLE);

        $trashed = $modelClass::onlyTrashed()->where('name', $attributes['name'])->first();

        if ($trashed) {
            $trashed->restore();
            $trashed->update($attributes);
            $item = $trashed;
        } else {
            $item = $modelClass::create($attributes);
        }

        // Load creator/editor để Resource trả created_by/updated_by — model tự gán 2 cột này
        // khi creating, nhưng whenLoaded() trả null nếu quan hệ chưa được nạp (giống show()).
        return $item->load(['creator.media', 'editor.media']);
    }

    public function update(Model $catalog, array $data): Model
    {
        $catalog->update(Arr::only($data, self::FILLABLE));

        return $catalog->load(['creator.media', 'editor.media']);
    }

    /**
     * Xoá bị chặn khi mục đang được tham chiếu (`restrictOnDelete` ở DB). Đếm trước rồi ném
     * lỗi có nghĩa, thay vì để SQLSTATE 23000 thô lọt ra ngoài.
     *
     * @throws CatalogInUseException
     */
    public function destroy(Model $catalog): void
    {
        $this->assertNotInUse($catalog);

        $catalog->delete();
    }

    /**
     * Xoá lần lượt chứ không `whereIn(...)->delete()`: cần biết mục nào đang được dùng để
     * báo tên cụ thể cho cán bộ. Danh mục có vài chục dòng nên vòng lặp không đáng ngại.
     */
    public function bulkDestroy(string $modelClass, array $ids): int
    {
        return DB::transaction(function () use ($modelClass, $ids) {
            $items = $modelClass::whereIn('id', $ids)->get();

            foreach ($items as $item) {
                $this->assertNotInUse($item);
            }

            return $modelClass::whereIn('id', $ids)->delete();
        });
    }

    public function bulkUpdateStatus(string $modelClass, array $ids, string $status): int
    {
        // update() qua Query Builder KHÔNG kích hoạt model event nên updated_at không tự
        // đổi — phải set tay, nếu không optimistic lock của các màn hình đang mở sẽ không
        // nhận ra dữ liệu đã đổi.
        return $modelClass::whereIn('id', $ids)->update([
            'status' => $status,
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ]);
    }

    public function changeStatus(Model $catalog, string $status): Model
    {
        $catalog->update(['status' => $status]);

        return $catalog->load(['creator.media', 'editor.media']);
    }

    /**
     * Sắp xếp lại thứ tự hiển thị trong dropdown.
     *
     * @param  array<int, array{id: int, sort_order: int}>  $items
     */
    public function reorder(string $modelClass, array $items): int
    {
        return DB::transaction(function () use ($modelClass, $items) {
            $updated = 0;

            foreach ($items as $item) {
                $updated += $modelClass::whereKey($item['id'])->update([
                    'sort_order' => $item['sort_order'],
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);
            }

            return $updated;
        });
    }

    public function exportQuery(string $modelClass, array $filters = []): Collection
    {
        return $this->index($modelClass, $filters, 1000000)
            ->getCollection()
            ->load(['creator.media', 'editor.media']);
    }

    /**
     * Tra ngược danh mục theo TÊN cho luồng import.
     *
     * Chuẩn hoá trim + không phân biệt hoa/thường để cán bộ gõ "thương binh" vẫn ra
     * "Thương binh". CHỈ khớp mục đang `active` — nhất quán với dropdown không cho chọn mục
     * đã ngừng dùng. Không khớp thì trả null, caller để trống chứ không chặn dòng.
     */
    public function findActiveIdByName(string $modelClass, ?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return $modelClass::query()
            ->active()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->value('id');
    }

    /** @throws CatalogInUseException */
    private function assertNotInUse(Model $catalog): void
    {
        $catalog->loadCount(self::USAGE_RELATIONS[$catalog::class]);

        $count = 0;
        foreach (self::USAGE_RELATIONS[$catalog::class] as $relation) {
            $count += (int) ($catalog->{$relation.'_count'} ?? 0);
        }

        if ($count > 0) {
            throw new CatalogInUseException($catalog->name, $count);
        }
    }
}
