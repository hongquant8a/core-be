<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryClassification;
use App\Modules\Beneficiary\Models\BeneficiaryDependentRelation;
use App\Modules\Beneficiary\Models\BeneficiaryDocument;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\ResidentialArea;
use Carbon\Carbon;

/**
 * Tổng hợp số liệu cho trang dashboard module Người có công.
 * Mọi truy vấn tự động scope theo tenant hiện tại (global scope của TenantModel);
 * riêng bảng không có organization_id (classifications, relations) scope gián tiếp
 * qua `whereHas('beneficiary')`.
 *
 * Mỗi phương thức breakdown trả mảng phần tử `{ key, label, total }` — FE dựng thẳng
 * bar/pie/line mà không cần map lại nhãn.
 */
class StatisticsService
{
    /** Gói toàn bộ số liệu cho 1 lần load dashboard. */
    public function overview(?int $year = null): array
    {
        return [
            'summary' => $this->summary(),
            'by_type' => $this->byType(),
            'by_status' => $this->byStatus(),
            'by_residential_area' => $this->byResidentialArea(),
            'by_gender' => $this->byGender(),
            'by_age_group' => $this->byAgeGroup(),
            'by_relationship' => $this->byRelationship(),
            'new_by_month' => $this->newByMonth($year),
        ];
    }

    /** Thẻ KPI tổng quan. */
    public function summary(): array
    {
        $byStatus = Beneficiary::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total_beneficiaries' => (int) $byStatus->sum(),
            'active_beneficiaries' => (int) ($byStatus[BeneficiaryStatusEnum::Active->value] ?? 0),
            'pending_beneficiaries' => (int) ($byStatus[BeneficiaryStatusEnum::Pending->value] ?? 0),
            'deceased_beneficiaries' => (int) ($byStatus[BeneficiaryStatusEnum::Deceased->value] ?? 0),
            'suspended_beneficiaries' => (int) ($byStatus[BeneficiaryStatusEnum::Suspended->value] ?? 0),
            'moved_out_beneficiaries' => (int) ($byStatus[BeneficiaryStatusEnum::MovedOut->value] ?? 0),
            'total_dependents' => Dependent::query()->count(),
            'total_households' => Household::query()->count(),
            'total_residential_areas' => ResidentialArea::query()->count(),
            'total_documents' => BeneficiaryDocument::query()->count(),
        ];
    }

    /** Số người có công theo loại đối tượng (1 người nhiều loại → đếm distinct theo từng loại). */
    public function byType(): array
    {
        $counts = BeneficiaryClassification::query()
            ->whereHas('beneficiary') // scope tenant qua quan hệ
            ->selectRaw('type, COUNT(DISTINCT beneficiary_id) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return $this->mapEnum(BeneficiaryTypeEnum::cases(), $counts);
    }

    /** Số người có công theo trạng thái. */
    public function byStatus(): array
    {
        $counts = Beneficiary::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->mapEnum(BeneficiaryStatusEnum::cases(), $counts);
    }

    /** Số người có công theo giới tính. */
    public function byGender(): array
    {
        $counts = Beneficiary::query()
            ->selectRaw('gender, COUNT(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        return $this->mapEnum(GenderEnum::cases(), $counts);
    }

    /** Số thân nhân theo loại quan hệ với người có công. */
    public function byRelationship(): array
    {
        $counts = BeneficiaryDependentRelation::query()
            ->whereHas('beneficiary')
            ->selectRaw('relationship_type, COUNT(*) as total')
            ->groupBy('relationship_type')
            ->pluck('total', 'relationship_type');

        return $this->mapEnum(DependentRelationshipEnum::cases(), $counts);
    }

    /**
     * Số người có công theo tổ dân phố / thôn — đọc thẳng `beneficiaries.residential_area_id`
     * (trường riêng của người có công), không còn suy ra qua hộ gia đình.
     */
    public function byResidentialArea(): array
    {
        $counts = Beneficiary::query()
            ->selectRaw('residential_area_id as area_id, COUNT(*) as total')
            ->groupBy('residential_area_id')
            ->pluck('total', 'area_id');

        $areas = ResidentialArea::query()->orderBy('name')->pluck('name', 'id');

        $result = [];
        foreach ($areas as $id => $name) {
            $result[] = ['key' => (int) $id, 'label' => $name, 'total' => (int) ($counts[$id] ?? 0)];
        }

        // Người có công chưa gán hộ / hộ chưa gán tổ dân phố.
        if ($unassigned = (int) ($counts[''] ?? $counts[null] ?? 0)) {
            $result[] = ['key' => null, 'label' => 'Chưa gán tổ dân phố', 'total' => $unassigned];
        }

        return $result;
    }

    /** Số hộ gia đình theo tổ dân phố / thôn. */
    public function householdsByArea(): array
    {
        $counts = Household::query()
            ->selectRaw('residential_area_id as area_id, COUNT(*) as total')
            ->groupBy('residential_area_id')
            ->pluck('total', 'area_id');

        $areas = ResidentialArea::query()->orderBy('name')->pluck('name', 'id');

        $result = [];
        foreach ($areas as $id => $name) {
            $result[] = ['key' => (int) $id, 'label' => $name, 'total' => (int) ($counts[$id] ?? 0)];
        }
        if ($unassigned = (int) ($counts[''] ?? $counts[null] ?? 0)) {
            $result[] = ['key' => null, 'label' => 'Chưa gán tổ dân phố', 'total' => $unassigned];
        }

        return $result;
    }

    /** Số người có công theo nhóm tuổi (tính từ date_of_birth hoặc birth_year). */
    public function byAgeGroup(): array
    {
        $currentYear = Carbon::now()->year;

        $buckets = [
            'under_60' => ['label' => 'Dưới 60', 'total' => 0],
            '60_69' => ['label' => '60 - 69', 'total' => 0],
            '70_79' => ['label' => '70 - 79', 'total' => 0],
            '80_89' => ['label' => '80 - 89', 'total' => 0],
            '90_plus' => ['label' => '90 trở lên', 'total' => 0],
            'unknown' => ['label' => 'Không rõ', 'total' => 0],
        ];

        Beneficiary::query()
            ->select(['date_of_birth', 'birth_year'])
            ->chunk(1000, function ($rows) use (&$buckets, $currentYear) {
                foreach ($rows as $row) {
                    $birthYear = $row->date_of_birth?->year
                        ?? (is_numeric($row->birth_year) ? (int) $row->birth_year : null);

                    if ($birthYear === null || $birthYear < 1900 || $birthYear > $currentYear) {
                        $buckets['unknown']['total']++;

                        continue;
                    }

                    $age = $currentYear - $birthYear;
                    $key = match (true) {
                        $age < 60 => 'under_60',
                        $age < 70 => '60_69',
                        $age < 80 => '70_79',
                        $age < 90 => '80_89',
                        default => '90_plus',
                    };
                    $buckets[$key]['total']++;
                }
            });

        $result = [];
        foreach ($buckets as $key => $b) {
            $result[] = ['key' => $key, 'label' => $b['label'], 'total' => $b['total']];
        }

        return $result;
    }

    /** Số người có công tiếp nhận mới theo từng tháng trong năm (mặc định năm hiện tại). */
    public function newByMonth(?int $year = null): array
    {
        $year = $year ?: Carbon::now()->year;

        $counts = Beneficiary::query()
            ->whereYear('created_at', $year)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $result[] = ['key' => $m, 'label' => "Tháng {$m}", 'total' => (int) ($counts[$m] ?? 0)];
        }

        return ['year' => $year, 'data' => $result];
    }

    /**
     * Map danh sách case enum → mảng {key,label,total}, đảm bảo giá trị 0 vẫn xuất hiện
     * (để biểu đồ luôn đủ trục), giữ đúng thứ tự khai báo enum.
     *
     * @param  array<int, \BackedEnum>  $cases
     */
    private function mapEnum(array $cases, $counts): array
    {
        return array_map(fn ($case) => [
            'key' => $case->value,
            'label' => method_exists($case, 'label') ? $case->label() : $case->value,
            'total' => (int) ($counts[$case->value] ?? 0),
        ], $cases);
    }
}
