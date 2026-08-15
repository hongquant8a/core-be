<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Events\BeneficiaryProfileSaved;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryDocument;
use App\Modules\Beneficiary\Models\BeneficiaryTypeRelation;
use App\Modules\Core\Exceptions\StaleRecordException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BeneficiaryService
{
    private const FILLABLE = [
        'full_name', 'birth_date', 'birth_year', 'gender', 'id_number', 'phone',
        'residential_area_id', 'address', 'latitude', 'longitude', 'note',
    ];

    private const TYPE_RELATION_FILLABLE = ['beneficiary_type_id', 'is_primary'];

    private const DEPENDENT_FILLABLE = [
        'full_name', 'birth_date', 'birth_year', 'gender', 'id_number', 'phone',
        'residential_area_id', 'address', 'latitude', 'longitude', 'note',
        'relationship_id', 'is_primary',
    ];

    private const DOCUMENT_FILLABLE = ['name', 'note'];

    /**
     * `creator.media` / `editor.media` chứ KHÔNG phải `creator` / `editor`:
     * `FormatsUserSummary` gọi `$user->getFirstMedia('avatars')`, nên chỉ load `creator` thì
     * mỗi user khác nhau sinh thêm một query. Load `creator.media` bao hàm luôn `creator`.
     */
    private const WITH_ALL = [
        'residentialArea',
        'typeRelations.media', 'typeRelations.beneficiaryType',
        'dependents.residentialArea', 'dependents.relationship',
        'documents.media',
        'creator.media', 'editor.media',
    ];

    private const WITH_LIST = ['residentialArea', 'creator.media', 'editor.media'];

    // ----------------------------------------------------------------------
    // Bộ action chuẩn của bảng chính
    // ----------------------------------------------------------------------

    /**
     * Module không có cột `status` nên `stats` không đếm theo trạng thái. Thay vào đó đếm
     * theo danh mục và theo mức độ đầy đủ dữ liệu — số hồ sơ thiếu toạ độ là thứ cán bộ
     * cần biết để hoàn thiện bản đồ.
     */
    public function stats(array $filters = []): array
    {
        $base = Beneficiary::query()
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d));

        return [
            'total' => (clone $base)->count(),
            'new_in_30_days' => (clone $base)->where('created_at', '>=', now()->subDays(30))->count(),
            'with_coordinates' => (clone $base)->whereNotNull('latitude')->whereNotNull('longitude')->count(),
            'without_coordinates' => (clone $base)->where(
                fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude')
            )->count(),
            'by_gender' => (clone $base)->selectRaw('gender, COUNT(*) as total')
                ->groupBy('gender')->pluck('total', 'gender'),
            'by_residential_area' => (clone $base)->join(
                'beneficiary_residential_areas as ra',
                'ra.id', '=', 'beneficiaries.residential_area_id'
            )->selectRaw('ra.name, COUNT(*) as total')->groupBy('ra.name')->pluck('total', 'name'),
            'by_type' => DB::table('beneficiary_type_relations as tr')
                ->join('beneficiary_types as t', 't.id', '=', 'tr.beneficiary_type_id')
                ->join('beneficiaries as b', 'b.id', '=', 'tr.beneficiary_id')
                ->whereNull('tr.deleted_at')
                ->whereNull('b.deleted_at')
                ->where('b.organization_id', getPermissionsTeamId())
                ->selectRaw('t.name, COUNT(DISTINCT tr.beneficiary_id) as total')
                ->groupBy('t.name')->pluck('total', 'name'),
        ];
    }

    public function index(array $filters = [], int $limit = 10): LengthAwarePaginator
    {
        return Beneficiary::query()
            ->with(self::WITH_LIST)
            ->when($filters['search'] ?? null, fn ($q, $kw) => $q->where(
                fn ($sub) => $sub->where('full_name', 'like', "%{$kw}%")
                    ->orWhere('id_number', 'like', "%{$kw}%")
                    ->orWhere('phone', 'like', "%{$kw}%")
            ))
            ->when($filters['residential_area_id'] ?? null, fn ($q, $id) => $q->where('residential_area_id', $id))
            ->when($filters['gender'] ?? null, fn ($q, $g) => $q->where('gender', $g))
            ->when($filters['birth_year_from'] ?? null, fn ($q, $y) => $q->where('birth_year', '>=', $y))
            ->when($filters['birth_year_to'] ?? null, fn ($q, $y) => $q->where('birth_year', '<=', $y))
            // Lọc qua bảng nối: whereHas chứ không join, để không nhân đôi dòng khi một
            // người thuộc nhiều loại đối tượng.
            ->when($filters['beneficiary_type_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'typeRelations', fn ($sub) => $sub->where('beneficiary_type_id', $id)
            ))
            ->when($filters['relationship_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'dependents', fn ($sub) => $sub->where('relationship_id', $id)
            ))
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderBy($filters['sort_by'] ?? 'id', $filters['sort_order'] ?? 'desc')
            ->paginate($limit);
    }

    public function show(Beneficiary $beneficiary): Beneficiary
    {
        return $beneficiary->load(self::WITH_ALL);
    }

    /**
     * CCCD là UNIQUE(organization_id, id_number) và bảng có SoftDeletes — dòng đã xoá mềm
     * vẫn chiếm chỗ trong unique index (đưa `deleted_at` vào unique không cứu được, MySQL
     * coi mọi NULL là khác nhau). Nhập lại đúng CCCD của một hồ sơ đã xoá phải là khôi phục
     * hồ sơ đó, không phải nhận SQLSTATE 23000.
     */
    public function store(array $data): Beneficiary
    {
        $attributes = Arr::only($data, self::FILLABLE);

        if (! empty($attributes['id_number'])) {
            $trashed = Beneficiary::withTrashed()
                ->onlyTrashed()
                ->where('id_number', $attributes['id_number'])
                ->first();

            if ($trashed) {
                $trashed->restore();
                $trashed->update($attributes);

                return $trashed;
            }
        }

        // organization_id do TenantModel tự gán — KHÔNG nhận từ client.
        return Beneficiary::create($attributes);
    }

    /**
     * Chỗ ghi bản chính DUY NHẤT — cả `BeneficiaryController::update` lẫn `saveFull` đều đi
     * qua đây (quy tắc 3 của B5). Optimistic lock nằm ở đây nên không đường nào bỏ sót được.
     */
    public function update(Beneficiary $beneficiary, array $data, ?string $clientLockVersion = null): Beneficiary
    {
        return DB::transaction(function () use ($beneficiary, $data, $clientLockVersion) {
            // Đọc lại kèm khoá dòng. Instance từ route model binding mang dữ liệu tại thời
            // điểm dispatch — hai request song song sẽ cùng pass nếu kiểm tra trên nó.
            // whereKey đi qua global scope tenant nên không khoá nhầm dòng của tổ chức khác.
            $locked = Beneficiary::whereKey($beneficiary->getKey())->lockForUpdate()->firstOrFail();

            $this->assertNotStale($locked, $clientLockVersion);

            $locked->update(Arr::only($data, self::FILLABLE));

            // touch() tường minh: không field nào dirty thì update() không đổi updated_at,
            // và optimistic lock của người khác vẫn thấy giá trị cũ.
            $locked->touch();

            return $locked;
        });
    }

    /**
     * Xoá mềm bản chính. `beneficiaries` có SoftDeletes nên `onDelete('cascade')` KHÔNG kích
     * hoạt — dòng con giữ nguyên và khôi phục lại được cùng bản chính.
     */
    public function destroy(Beneficiary $beneficiary): void
    {
        $beneficiary->delete();
    }

    public function bulkDestroy(array $ids): int
    {
        // Query đi qua global scope tenant nên id của tổ chức khác tự rơi ra ngoài.
        return Beneficiary::whereIn('id', $ids)->delete();
    }

    /** Dữ liệu cho Export — nạp đủ quan hệ để liệt kê các cột 1–N. */
    public function exportQuery(array $filters = []): Collection
    {
        return $this->index($filters, 1000000)
            ->getCollection()
            ->load(self::WITH_ALL);
    }

    // ----------------------------------------------------------------------
    // Endpoint gộp (quy tắc 2 của B5)
    // ----------------------------------------------------------------------

    /**
     * CẤM gọi từ màn hình có phân trang.
     *
     * `whereNotIn` xoá mọi dòng không có trong mảng gửi lên. Frontend chỉ giữ một trang
     * trong state thì toàn bộ phần chưa load bị xoá mềm và response vẫn 200. Ràng buộc này
     * KHÔNG kiểm chứng được ở backend.
     */
    public function saveFull(?Beneficiary $beneficiary, array $data): Beneficiary
    {
        $trashMedia = collect();
        $pendingUploads = [];

        $saved = DB::transaction(function () use ($beneficiary, $data, &$trashMedia, &$pendingUploads) {
            // Quy tắc 3: không tự ghi bản chính.
            $beneficiary = $beneficiary
                ? $this->update($beneficiary, $data, $data['lock_version'] ?? null)
                : $this->store($data);

            // array_key_exists chứ không phải empty(): mảng đến từ JSON đã decode nên
            // [] (xoá hết) và vắng mặt (không quản lý) là hai trạng thái phân biệt được.
            if (array_key_exists('type_relations', $data)) {
                $trashMedia = $trashMedia->merge($this->syncTypeRelations(
                    $beneficiary, $data['type_relations'], $data['type_relations_files'] ?? [], $pendingUploads
                ));
            }

            if (array_key_exists('dependents', $data)) {
                $this->syncDependents($beneficiary, $data['dependents']);
            }

            if (array_key_exists('documents', $data)) {
                $trashMedia = $trashMedia->merge($this->syncDocuments(
                    $beneficiary, $data['documents'], $data['documents_files'] ?? [], $pendingUploads
                ));
            }

            // whereNotIn xoá qua Query Builder nên KHÔNG kích hoạt $touches. Nếu request chỉ
            // xoá dòng con thì không model nào được save và beneficiaries.updated_at đứng
            // yên — optimistic lock mù đúng vào thao tác phá hoại nhất.
            $beneficiary->touch();

            return $beneficiary;
        });

        // THỨ TỰ KHÔNG ĐƯỢC ĐỔI:
        //   1. snapshot media  (đã làm trong transaction, TRƯỚC mọi ghi tệp)
        //   2. commit
        //   3. ghi tệp mới
        //   4. xoá tệp cũ
        //
        // Ghi tệp nằm ngoài transaction vì trong đó có lockForUpdate() trên dòng cha — giữ
        // khoá suốt thời gian copy hàng chục tệp khiến request thứ hai chờ tới
        // innodb_lock_wait_timeout (mặc định 50s) rồi 500, thay vì nhận 409 sạch sẽ.
        foreach ($pendingUploads as [$model, $collection, $files]) {
            foreach ($files as $file) {
                if ($file->isValid()) {
                    $model->addMedia($file)->toMediaCollection($collection);
                }
            }
        }

        // Xoá tệp cũ SAU CÙNG: bước ghi trên ném lỗi thì tệp cũ vẫn còn nguyên.
        $trashMedia->each->delete();

        // Fire event ở ĐÂY, không dùng ShouldDispatchAfterCommit: "after commit" vẫn sớm hơn
        // thời điểm tệp yên vị. Đây là ngoại lệ có chủ đích của quy tắc C2.
        event(new BeneficiaryProfileSaved($saved->id, $saved->organization_id));

        return $saved->fresh(self::WITH_ALL);
    }

    // ----------------------------------------------------------------------
    // Bulk sync danh sách con (chỉ dùng bởi saveFull)
    // ----------------------------------------------------------------------

    /**
     * Dạng D — n–n có thuộc tính, xử lý y hệt dạng A.
     *
     * Khác biệt duy nhất: UNIQUE(beneficiary_id, beneficiary_type_id) cộng SoftDeletes tạo
     * ra bẫy. Cán bộ bỏ một loại đối tượng rồi thêm lại sẽ nhận SQLSTATE 23000 giữa
     * transaction nếu create() thẳng — phải tìm cả dòng đã xoá mềm rồi restore(), nhờ vậy
     * tệp đính kèm cũ quay lại nguyên vẹn.
     *
     * @param  array  $filesByIndex  type_relations_files[i][] — khớp theo CHỈ SỐ dòng
     * @param  array  $pendingUploads  gom lại để ghi sau commit
     */
    private function syncTypeRelations(
        Beneficiary $beneficiary,
        array $rows,
        array $filesByIndex,
        array &$pendingUploads
    ): Collection {
        $rows = $this->normalizePrimaryFlags($rows);

        // Chụp id hiện có để (1) phân biệt update với create và (2) chặn client gửi id
        // thuộc bản ghi cha khác.
        $existingIds = $beneficiary->typeRelations()->pluck('id')->all();
        $keepIds = [];
        $allTrashMedia = collect();

        foreach ($rows as $index => $row) {
            $attributes = Arr::only($row, self::TYPE_RELATION_FILLABLE);

            if (! empty($row['id']) && in_array((int) $row['id'], $existingIds, true)) {
                // findOrFail chạy TRÊN quan hệ nên đã giới hạn trong phạm vi cha — chặn
                // IDOR. KHÔNG ĐƯỢC đổi thành BeneficiaryTypeRelation::find().
                $item = tap($beneficiary->typeRelations()->findOrFail($row['id']))->update($attributes);
            } else {
                $item = $beneficiary->typeRelations()
                    ->withTrashed()
                    ->where('beneficiary_type_id', $attributes['beneficiary_type_id'])
                    ->first();

                if ($item) {
                    if ($item->trashed()) {
                        $item->restore();
                    }
                    $item->update($attributes);
                } else {
                    $item = $beneficiary->typeRelations()->create($attributes);
                }
            }

            $keepIds[] = $item->id;

            // SNAPSHOT phải chụp TRƯỚC khi có bất kỳ tệp mới nào được ghi.
            $existingMedia = $item->getMedia(BeneficiaryTypeRelation::MEDIA_COLLECTION);

            if (! empty($filesByIndex[$index])) {
                $pendingUploads[] = [$item, BeneficiaryTypeRelation::MEDIA_COLLECTION, $filesByIndex[$index]];
            }

            if ($row['sync_attachments'] ?? false) {
                // Duyệt trên $existingMedia (media của CHÍNH record này) nên client gửi id
                // lạ cũng không xoá được tệp của bản ghi khác.
                $keep = array_map('intval', $row['keep_media_ids'] ?? []);
                $allTrashMedia = $allTrashMedia->merge(
                    $existingMedia->reject(fn ($media) => in_array((int) $media->id, $keep, true))
                );
            }
        }

        $beneficiary->typeRelations()->whereNotIn('id', $keepIds)->delete();

        return $allTrashMedia;
    }

    /** Dạng B — bỏ toàn bộ phần media. */
    private function syncDependents(Beneficiary $beneficiary, array $rows): void
    {
        $rows = $this->normalizePrimaryFlags($rows);

        $existingIds = $beneficiary->dependents()->pluck('id')->all();
        $keepIds = [];

        foreach ($rows as $row) {
            $attributes = Arr::only($row, self::DEPENDENT_FILLABLE);

            $item = ! empty($row['id']) && in_array((int) $row['id'], $existingIds, true)
                ? tap($beneficiary->dependents()->findOrFail($row['id']))->update($attributes)
                : $beneficiary->dependents()->create($attributes);

            $keepIds[] = $item->id;
        }

        $beneficiary->dependents()->whereNotIn('id', $keepIds)->delete();
    }

    /** Dạng A — 1–n có tệp. Không có unique constraint nên không cần nhánh restore. */
    private function syncDocuments(
        Beneficiary $beneficiary,
        array $rows,
        array $filesByIndex,
        array &$pendingUploads
    ): Collection {
        $existingIds = $beneficiary->documents()->pluck('id')->all();
        $keepIds = [];
        $allTrashMedia = collect();

        foreach ($rows as $index => $row) {
            $attributes = Arr::only($row, self::DOCUMENT_FILLABLE);

            $item = ! empty($row['id']) && in_array((int) $row['id'], $existingIds, true)
                ? tap($beneficiary->documents()->findOrFail($row['id']))->update($attributes)
                : $beneficiary->documents()->create($attributes);

            $keepIds[] = $item->id;

            $existingMedia = $item->getMedia(BeneficiaryDocument::MEDIA_COLLECTION);

            if (! empty($filesByIndex[$index])) {
                $pendingUploads[] = [$item, BeneficiaryDocument::MEDIA_COLLECTION, $filesByIndex[$index]];
            }

            if ($row['sync_attachments'] ?? false) {
                $keep = array_map('intval', $row['keep_media_ids'] ?? []);
                $allTrashMedia = $allTrashMedia->merge(
                    $existingMedia->reject(fn ($media) => in_array((int) $media->id, $keep, true))
                );
            }
        }

        $beneficiary->documents()->whereNotIn('id', $keepIds)->delete();

        return $allTrashMedia;
    }

    /**
     * "Nhiều nhất một dòng chính", không phải "đúng một".
     *
     * Client gửi nhiều dòng `is_primary = true` thì dòng ĐẦU TIÊN thắng, phần còn lại bị hạ
     * xuống false. Không có dòng nào là chính cũng hợp lệ — hồ sơ mới nhập chưa xác định.
     *
     * Chuẩn hoá tại đây thay vì sau khi ghi: một vòng UPDATE thêm sau mỗi lần lưu vừa tốn
     * query vừa để lộ trạng thái nhiều dòng cùng chính cho request đọc song song.
     *
     * @param  array<int, array>  $rows
     * @return array<int, array>
     */
    private function normalizePrimaryFlags(array $rows): array
    {
        $seenPrimary = false;

        foreach ($rows as $index => $row) {
            if (! array_key_exists('is_primary', $row)) {
                continue;
            }

            if (filter_var($row['is_primary'], FILTER_VALIDATE_BOOLEAN) && ! $seenPrimary) {
                $rows[$index]['is_primary'] = true;
                $seenPrimary = true;
            } else {
                $rows[$index]['is_primary'] = false;
            }
        }

        return $rows;
    }

    /**
     * So theo Unix timestamp (giây), KHÔNG dùng `Carbon::ne()`.
     *
     * `ne()` so đến micro-giây, trong khi `lock_version` gửi cho frontend chỉ xuất đến giây.
     * Cột `timestamps()` hiện không có phần thập phân nên còn khớp, nhưng đổi sang
     * `timestamp(6)` là mọi request update 409 vĩnh viễn.
     */
    private function assertNotStale(Beneficiary $beneficiary, ?string $clientLockVersion): void
    {
        if (! $clientLockVersion) {
            return;
        }

        if ($beneficiary->updated_at?->timestamp !== Carbon::parse($clientLockVersion)->timestamp) {
            throw new StaleRecordException();
        }
    }
}
