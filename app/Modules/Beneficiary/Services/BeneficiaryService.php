<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Enums\GenderEnum;
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

    /**
     * Nhóm tuổi dùng cho biểu đồ phân bố và tháp tuổi × giới. Người có công phần lớn cao
     * tuổi nên các rổ nghiêng về nhóm già; hồ sơ không biết năm sinh rơi vào 'Không rõ'.
     *
     * @var array<string, array{int, int}>
     */
    private const AGE_GROUPS = [
        '0-29' => [0, 29],
        '30-44' => [30, 44],
        '45-59' => [45, 59],
        '60-74' => [60, 74],
        '75-89' => [75, 89],
        '90+' => [90, 200],
    ];

    private const AGE_UNKNOWN = 'Không rõ';

    /**
     * Dữ liệu cho trang thống kê (dashboard) — 6 chỉ số tổng, 8 biểu đồ, 3 bảng tổng hợp.
     *
     * Gộp trong MỘT endpoint (`GET /beneficiaries/dashboard`) thay vì mỗi widget một route:
     * FE mở trang gọi một lần. Khác `stats()` (nhẹ, cho badge đầu màn danh sách) ở chỗ nó
     * trả trọn bộ cụm biểu đồ đã gán nhãn tiếng Việt, format `[{label, value}]` để FE render
     * thẳng, không cần Resource.
     *
     * Nhận cùng bộ lọc của `FilterBeneficiaryRequest`: `from_date`, `to_date` (kỳ báo cáo,
     * lọc theo `created_at`) và `residential_area_id` (xem riêng một tổ dân phố).
     */
    public function dashboard(array $filters = []): array
    {
        return [
            'kpis' => $this->dashboardKpis($filters),
            'charts' => [
                'by_gender' => $this->chartByGender($filters),
                'by_type' => $this->chartByType($filters),
                'by_residential_area' => $this->chartByResidentialArea($filters),
                'by_age_group' => $this->chartByAgeGroup($filters),
                'age_gender_pyramid' => $this->chartAgeGenderPyramid($filters),
                'created_trend_12m' => $this->chartCreatedTrend($filters),
                'dependents_by_relationship' => $this->chartDependentsByRelationship($filters),
                'data_quality' => $this->chartDataQuality($filters),
            ],
            'tables' => [
                'area_type_matrix' => $this->tableAreaTypeMatrix($filters),
                'type_summary' => $this->tableTypeSummary($filters),
                'incomplete_profiles' => $this->tableIncompleteProfiles($filters),
            ],
        ];
    }

    // ----------------------------------------------------------------------
    // Dashboard — chỉ số tổng (KPI)
    // ----------------------------------------------------------------------

    private function dashboardKpis(array $filters): array
    {
        $base = $this->dashboardBase($filters);

        $total = (clone $base)->count();
        $withCoordinates = (clone $base)
            ->whereNotNull('latitude')->whereNotNull('longitude')->count();

        return [
            'total' => $total,
            'new_in_30_days' => (clone $base)->where('created_at', '>=', now()->subDays(30))->count(),
            'total_type_relations' => $this->childBase('beneficiary_type_relations', $filters)->count(),
            'total_dependents' => $this->childBase('beneficiary_dependents', $filters)->count(),
            // Làm tròn 1 chữ số; 0 hồ sơ thì tránh chia cho 0.
            'with_coordinates_percent' => $total > 0 ? round($withCoordinates / $total * 100, 1) : 0.0,
            'incomplete_count' => (clone $base)->where(fn ($q) => $q
                ->whereNull('id_number')->orWhere('id_number', '')
                ->orWhereNull('birth_year')
                ->orWhereNull('latitude')->orWhereNull('longitude')
            )->count(),
        ];
    }

    // ----------------------------------------------------------------------
    // Dashboard — 8 biểu đồ
    // ----------------------------------------------------------------------

    /** #1 Cơ cấu giới tính (donut). Null → "Chưa xác định". */
    private function chartByGender(array $filters): array
    {
        $counts = $this->dashboardBase($filters)
            ->selectRaw('gender, COUNT(*) as total')->groupBy('gender')
            ->pluck('total', 'gender');

        return $counts->map(fn ($total, $gender) => [
            'label' => $this->genderLabel($gender === '' ? null : $gender),
            'value' => (int) $total,
        ])->values()->all();
    }

    /** #2 Cơ cấu theo loại đối tượng (cột ngang) — đếm DISTINCT hồ sơ vì n–n. */
    private function chartByType(array $filters): array
    {
        return $this->typeRelationBase($filters)
            ->join('beneficiary_types as t', 't.id', '=', 'tr.beneficiary_type_id')
            ->selectRaw('t.name, COUNT(DISTINCT tr.beneficiary_id) as total')
            ->groupBy('t.name')->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['label' => $r->name, 'value' => (int) $r->total])
            ->all();
    }

    /** #3 Phân bố theo tổ dân phố (Top 10 + "Khác"). Hồ sơ chưa gán tổ → "Chưa phân tổ". */
    private function chartByResidentialArea(array $filters): array
    {
        $rows = $this->dashboardBase($filters)
            ->leftJoin('beneficiary_residential_areas as ra', 'ra.id', '=', 'beneficiaries.residential_area_id')
            ->selectRaw("COALESCE(ra.name, 'Chưa phân tổ') as name, COUNT(*) as total")
            ->groupBy('name')->orderByDesc('total')
            ->get();

        return $this->topNWithOther($rows->map(fn ($r) => [
            'label' => $r->name, 'value' => (int) $r->total,
        ])->all(), 10);
    }

    /** #4 Phân bố nhóm tuổi (cột). Bucket trong PHP để không phụ thuộc SQL từng loại DB. */
    private function chartByAgeGroup(array $filters): array
    {
        $byYear = $this->dashboardBase($filters)
            ->selectRaw('birth_year, COUNT(*) as total')->groupBy('birth_year')
            ->pluck('total', 'birth_year');

        $buckets = array_fill_keys([...array_keys(self::AGE_GROUPS), self::AGE_UNKNOWN], 0);
        $currentYear = (int) now()->year;

        foreach ($byYear as $year => $total) {
            $group = $year ? $this->ageGroupOf($currentYear - (int) $year) : self::AGE_UNKNOWN;
            $buckets[$group] += (int) $total;
        }

        return array_map(
            fn ($label, $value) => ['label' => $label, 'value' => $value],
            array_keys($buckets), array_values($buckets)
        );
    }

    /** #5 Tháp tuổi × giới. Mỗi nhóm tuổi tách nam/nữ/khác/chưa rõ. */
    private function chartAgeGenderPyramid(array $filters): array
    {
        $rows = $this->dashboardBase($filters)
            ->selectRaw('birth_year, gender, COUNT(*) as total')
            ->groupBy('birth_year', 'gender')->get();

        $groups = [...array_keys(self::AGE_GROUPS), self::AGE_UNKNOWN];
        $pyramid = [];
        foreach ($groups as $g) {
            $pyramid[$g] = ['age_group' => $g, 'male' => 0, 'female' => 0, 'other' => 0, 'unknown' => 0];
        }

        $currentYear = (int) now()->year;
        foreach ($rows as $row) {
            $group = $row->birth_year ? $this->ageGroupOf($currentYear - (int) $row->birth_year) : self::AGE_UNKNOWN;
            $key = match ($row->gender) {
                GenderEnum::Male->value => 'male',
                GenderEnum::Female->value => 'female',
                GenderEnum::Other->value => 'other',
                default => 'unknown',
            };
            $pyramid[$group][$key] += (int) $row->total;
        }

        return array_values($pyramid);
    }

    /**
     * #6 Tiến độ nhập hồ sơ 12 tháng gần nhất (đường).
     *
     * CỐ Ý bỏ qua `from_date`/`to_date`: biểu đồ luôn phủ 12 tháng gần nhất theo tên gọi.
     * Chỉ tôn trọng `residential_area_id`. Lưu ý nghiệp vụ: đây là tiến độ NHẬP LIỆU của cán
     * bộ (theo `created_at`), KHÔNG phải tăng/giảm số người có công thực tế — FE cần ghi nhãn.
     */
    private function chartCreatedTrend(array $filters): array
    {
        $since = now()->startOfMonth()->subMonths(11);

        $counts = Beneficiary::query()
            ->when($filters['residential_area_id'] ?? null, fn ($q, $id) => $q->where('residential_area_id', $id))
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')->pluck('total', 'ym');

        // Dựng đủ 12 mốc kể cả tháng không có hồ sơ nào (điền 0) để đường liền mạch.
        $series = [];
        for ($i = 0; $i < 12; $i++) {
            $month = (clone $since)->addMonths($i);
            $series[] = [
                'label' => $month->format('m/Y'),
                'value' => (int) ($counts[$month->format('Y-m')] ?? 0),
            ];
        }

        return $series;
    }

    /** #7 Thân nhân theo mối quan hệ (donut). Thân nhân chưa gán quan hệ → "Chưa phân loại". */
    private function chartDependentsByRelationship(array $filters): array
    {
        return $this->dependentBase($filters)
            ->leftJoin('beneficiary_relationships as r', 'r.id', '=', 'd.relationship_id')
            ->selectRaw("COALESCE(r.name, 'Chưa phân loại') as name, COUNT(*) as total")
            ->groupBy('name')->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['label' => $r->name, 'value' => (int) $r->total])
            ->all();
    }

    /** #8 Chất lượng dữ liệu (cột) — bốn chỉ số độc lập, một hồ sơ có thể thiếu nhiều thứ. */
    private function chartDataQuality(array $filters): array
    {
        $base = $this->dashboardBase($filters);

        return [
            ['label' => 'Đủ toạ độ', 'value' => (clone $base)
                ->whereNotNull('latitude')->whereNotNull('longitude')->count()],
            ['label' => 'Thiếu toạ độ', 'value' => (clone $base)
                ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))->count()],
            ['label' => 'Thiếu CCCD/CMND', 'value' => (clone $base)
                ->where(fn ($q) => $q->whereNull('id_number')->orWhere('id_number', ''))->count()],
            ['label' => 'Thiếu năm sinh', 'value' => (clone $base)->whereNull('birth_year')->count()],
        ];
    }

    // ----------------------------------------------------------------------
    // Dashboard — 3 bảng tổng hợp
    // ----------------------------------------------------------------------

    /**
     * Bảng chéo Tổ dân phố × Loại đối tượng — báo cáo hành chính chuẩn.
     * Trả `types` (tên cột) + `rows` (mỗi tổ một dòng, `counts` theo từng loại + `total`).
     */
    private function tableAreaTypeMatrix(array $filters): array
    {
        $rows = $this->typeRelationBase($filters)
            ->join('beneficiary_types as t', 't.id', '=', 'tr.beneficiary_type_id')
            ->leftJoin('beneficiary_residential_areas as ra', 'ra.id', '=', 'b.residential_area_id')
            ->selectRaw("COALESCE(ra.name, 'Chưa phân tổ') as area, t.name as type, COUNT(DISTINCT tr.beneficiary_id) as total")
            ->groupBy('area', 'type')->get();

        $types = $rows->pluck('type')->unique()->sort()->values()->all();

        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row->area] ??= ['area' => $row->area, 'counts' => array_fill_keys($types, 0), 'total' => 0];
            $matrix[$row->area]['counts'][$row->type] = (int) $row->total;
            $matrix[$row->area]['total'] += (int) $row->total;
        }

        // Tổ nhiều đối tượng nhất lên đầu.
        $ordered = array_values($matrix);
        usort($ordered, fn ($a, $b) => $b['total'] <=> $a['total']);

        return ['types' => $types, 'rows' => $ordered];
    }

    /** Tổng hợp theo loại đối tượng: số lượng + tỉ lệ % trên tổng hồ sơ. */
    private function tableTypeSummary(array $filters): array
    {
        $total = $this->dashboardBase($filters)->count();

        return $this->typeRelationBase($filters)
            ->join('beneficiary_types as t', 't.id', '=', 'tr.beneficiary_type_id')
            ->selectRaw('t.name, COUNT(DISTINCT tr.beneficiary_id) as total')
            ->groupBy('t.name')->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'total' => (int) $r->total,
                'percent' => $total > 0 ? round($r->total / $total * 100, 1) : 0.0,
            ])->all();
    }

    /**
     * Danh sách hồ sơ cần hoàn thiện (thiếu CCCD / năm sinh / toạ độ) — tối đa 50 dòng để cán
     * bộ bắt tay hoàn thiện. Tổng số đầy đủ nằm ở KPI `incomplete_count`.
     */
    private function tableIncompleteProfiles(array $filters): array
    {
        return $this->dashboardBase($filters)
            ->with('residentialArea:id,name')
            ->where(fn ($q) => $q
                ->whereNull('id_number')->orWhere('id_number', '')
                ->orWhereNull('birth_year')
                ->orWhereNull('latitude')->orWhereNull('longitude'))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'full_name', 'id_number', 'birth_year', 'residential_area_id', 'latitude', 'longitude', 'created_at'])
            ->map(function (Beneficiary $b) {
                $missing = [];
                if (empty($b->id_number)) {
                    $missing[] = 'CCCD/CMND';
                }
                if (empty($b->birth_year)) {
                    $missing[] = 'Năm sinh';
                }
                if ($b->latitude === null || $b->longitude === null) {
                    $missing[] = 'Toạ độ';
                }

                return [
                    'id' => $b->id,
                    'full_name' => $b->full_name,
                    'residential_area' => $b->residentialArea?->name,
                    'missing' => $missing,
                ];
            })->all();
    }

    // ----------------------------------------------------------------------
    // Dashboard — helper dùng chung
    // ----------------------------------------------------------------------

    /** Query bảng chính đã áp bộ lọc dashboard (đi qua global scope tenant của TenantModel). */
    private function dashboardBase(array $filters)
    {
        return $this->applyBeneficiaryFilters(Beneficiary::query(), $filters, 'beneficiaries');
    }

    /**
     * Áp bộ lọc dashboard lên cột của bảng `beneficiaries` (hoặc alias `b` khi join từ bảng
     * con). Dùng chung để logic lọc chỉ nằm một chỗ.
     */
    private function applyBeneficiaryFilters($query, array $filters, string $alias)
    {
        return $query
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate("$alias.created_at", '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate("$alias.created_at", '<=', $d))
            ->when($filters['residential_area_id'] ?? null, fn ($q, $id) => $q->where("$alias.residential_area_id", $id));
    }

    /**
     * Query đếm dòng con (đối tượng/thân nhân) join sang `beneficiaries` đã lọc.
     * Bảng con không có global scope trong ngữ cảnh raw nên tự scope tenant + loại bản ghi
     * đã xoá mềm ở cả hai bảng — cùng khuôn với `stats()->by_type`.
     */
    private function childBase(string $childTable, array $filters)
    {
        return $this->applyBeneficiaryFilters(
            DB::table("$childTable as c")
                ->join('beneficiaries as b', 'b.id', '=', 'c.beneficiary_id')
                ->whereNull('c.deleted_at')->whereNull('b.deleted_at')
                ->where('b.organization_id', getPermissionsTeamId()),
            $filters, 'b'
        );
    }

    /** Như childBase nhưng alias cố định `tr` cho bảng đối tượng (dùng khi còn join tiếp). */
    private function typeRelationBase(array $filters)
    {
        return $this->applyBeneficiaryFilters(
            DB::table('beneficiary_type_relations as tr')
                ->join('beneficiaries as b', 'b.id', '=', 'tr.beneficiary_id')
                ->whereNull('tr.deleted_at')->whereNull('b.deleted_at')
                ->where('b.organization_id', getPermissionsTeamId()),
            $filters, 'b'
        );
    }

    /** Như childBase nhưng alias cố định `d` cho bảng thân nhân. */
    private function dependentBase(array $filters)
    {
        return $this->applyBeneficiaryFilters(
            DB::table('beneficiary_dependents as d')
                ->join('beneficiaries as b', 'b.id', '=', 'd.beneficiary_id')
                ->whereNull('d.deleted_at')->whereNull('b.deleted_at')
                ->where('b.organization_id', getPermissionsTeamId()),
            $filters, 'b'
        );
    }

    private function genderLabel(?string $gender): string
    {
        if ($gender === null) {
            return 'Chưa xác định';
        }

        return GenderEnum::tryFrom($gender)?->label() ?? $gender;
    }

    private function ageGroupOf(int $age): string
    {
        foreach (self::AGE_GROUPS as $label => [$min, $max]) {
            if ($age >= $min && $age <= $max) {
                return $label;
            }
        }

        // Tuổi âm (năm sinh > năm hiện tại do nhập sai) — gom vào nhóm trẻ nhất.
        return array_key_first(self::AGE_GROUPS);
    }

    /**
     * Giữ Top N mục lớn nhất, gộp phần còn lại vào "Khác" (giả định đầu vào đã sắp giảm dần).
     *
     * @param  array<int, array{label: string, value: int}>  $items
     * @return array<int, array{label: string, value: int}>
     */
    private function topNWithOther(array $items, int $n): array
    {
        if (count($items) <= $n) {
            return $items;
        }

        $top = array_slice($items, 0, $n);
        $otherValue = array_sum(array_column(array_slice($items, $n), 'value'));
        $top[] = ['label' => 'Khác', 'value' => $otherValue];

        return $top;
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
