<?php

namespace Database\Seeders;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentEligibilityEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use App\Modules\Beneficiary\Enums\DependentRelationStatusEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Enums\ScheduleStatusEnum;
use App\Modules\Beneficiary\Enums\SubsidyStatusEnum;
use App\Modules\Beneficiary\Enums\VisitOccasionEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryClassification;
use App\Modules\Beneficiary\Models\BeneficiaryDependentRelation;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\ResidentialArea;
use App\Modules\Beneficiary\Models\StatusHistory;
use App\Modules\Beneficiary\Models\SubsidyGrant;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use App\Modules\Beneficiary\Models\VisitSchedule;
use App\Modules\Core\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed đầy đủ danh mục (chính sách trợ cấp, tổ dân phố) + dữ liệu nghiệp vụ mẫu
 * (hộ gia đình, người có công đủ 12 nhóm đối tượng, thân nhân, trợ cấp đã cấp,
 * lịch sử đổi trạng thái, lịch thăm hỏi) cho tenant mặc định — phục vụ demo sản
 * phẩm cho khách hàng với dữ liệu đầy đủ, thực tế.
 * Mức trợ cấp là số liệu THAM KHẢO — cần đối chiếu số liệu thật với Sở LĐTBXH trước khi dùng thật.
 */
class BeneficiaryDataSeeder extends Seeder
{
    private const ORG_ID = 1;

    public function run(): void
    {
        auth()->setUser(User::first());

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(self::ORG_ID);
        }

        $policies = $this->seedSubsidyPolicies();
        $areas = $this->seedResidentialAreas();
        $households = $this->seedHouseholds($areas);
        $beneficiaries = $this->seedBeneficiaries($households);
        $this->seedClassifications($beneficiaries);
        $dependents = $this->seedDependents($households);
        $this->seedDependentRelations($beneficiaries, $dependents);
        $this->seedSubsidyGrants($beneficiaries, $dependents, $policies);
        $this->seedStatusHistories($beneficiaries);
        $this->seedVisitSchedules($beneficiaries, $households, $dependents);

        auth()->forgetUser();
    }

    /** Danh mục chính sách trợ cấp — phủ đủ 12 nhóm người có công + 1 chính sách theo quan hệ thân nhân. */
    protected function seedSubsidyPolicies(): array
    {
        $now = Carbon::parse('2021-07-01 00:00:00');

        $data = [
            'pre_revolution_1945' => ['type' => BeneficiaryTypeEnum::PreRevolution1945, 'amount' => 2_200_000, 'unit' => 'VND/tháng'],
            'revolution_1945_to_1945_uprising' => ['type' => BeneficiaryTypeEnum::Revolution1945To1945Uprising, 'amount' => 2_050_000, 'unit' => 'VND/tháng'],
            'martyr' => ['type' => BeneficiaryTypeEnum::Martyr, 'amount' => 1_600_000, 'unit' => 'VND/năm', 'note' => ' (trợ cấp thờ cúng liệt sĩ)'],
            'vietnamese_heroic_mother' => ['type' => BeneficiaryTypeEnum::VietnameseHeroicMother, 'amount' => 4_800_000, 'unit' => 'VND/tháng'],
            'hero_of_armed_forces' => ['type' => BeneficiaryTypeEnum::HeroOfArmedForces, 'amount' => 1_470_000, 'unit' => 'VND/tháng'],
            'hero_of_labor' => ['type' => BeneficiaryTypeEnum::HeroOfLabor, 'amount' => 1_470_000, 'unit' => 'VND/tháng'],
            'war_invalid' => ['type' => BeneficiaryTypeEnum::WarInvalid, 'amount' => 3_500_000, 'unit' => 'VND/tháng'],
            'disease_invalid' => ['type' => BeneficiaryTypeEnum::DiseaseInvalid, 'amount' => 3_200_000, 'unit' => 'VND/tháng'],
            'agent_orange_victim' => ['type' => BeneficiaryTypeEnum::AgentOrangeVictim, 'amount' => 2_800_000, 'unit' => 'VND/tháng'],
            'former_prisoner' => ['type' => BeneficiaryTypeEnum::FormerPrisoner, 'amount' => 1_200_000, 'unit' => 'VND/tháng'],
            'resistance_activist' => ['type' => BeneficiaryTypeEnum::ResistanceActivist, 'amount' => 2_100_000, 'unit' => 'VND/tháng'],
            'revolution_supporter' => ['type' => BeneficiaryTypeEnum::RevolutionSupporter, 'amount' => 1_000_000, 'unit' => 'VND/tháng'],
            'dependent_child_care' => ['relationship' => 'child', 'amount' => 810_000, 'unit' => 'VND/tháng', 'note' => ' (trợ cấp tiền tuất nuôi dưỡng con)'],
        ];

        $records = [];
        foreach ($data as $key => $row) {
            $where = [
                'organization_id' => null,
                'beneficiary_type' => $row['type']->value ?? null,
                'relationship_type' => $row['relationship'] ?? null,
                'effective_from' => $now,
            ];

            $record = SubsidyPolicy::unguarded(fn () => SubsidyPolicy::withoutGlobalScopes()->firstOrCreate(
                $where,
                [
                    'amount' => $row['amount'],
                    'unit' => $row['unit'],
                    'legal_basis' => 'Nghị định 75/2021/NĐ-CP'.($row['note'] ?? ''),
                ]
            ));

            DB::table('beneficiary_subsidy_policies')->where('id', $record->id)->update([
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $records[$key] = $record;
        }

        return $records;
    }

    /** Danh mục tổ dân phố thuộc phường Hải Châu / Thanh Khê, TP Đà Nẵng. */
    protected function seedResidentialAreas(): array
    {
        $now = Carbon::parse('2026-01-12 08:00:00');
        $creatorId = User::first()?->id;

        $data = [
            ['name' => 'Tổ 1', 'code' => 'TDP-001'],
            ['name' => 'Tổ 2', 'code' => 'TDP-002'],
            ['name' => 'Tổ 3', 'code' => 'TDP-003'],
            ['name' => 'Tổ 4', 'code' => 'TDP-004'],
            ['name' => 'Tổ 5', 'code' => 'TDP-005'],
        ];

        $areas = [];
        foreach ($data as $i => $row) {
            $area = ResidentialArea::unguarded(fn () => ResidentialArea::withoutGlobalScopes()->firstOrCreate(
                ['name' => $row['name'], 'organization_id' => self::ORG_ID],
                ['code' => $row['code']]
            ));
            $this->stamp('beneficiary_residential_areas', $area->id, $now->copy()->addMinutes($i), $creatorId);
            $areas[] = $area;
        }

        return $areas;
    }

    /** 10 hộ gia đình, phân bố đều trên 5 tổ dân phố. */
    protected function seedHouseholds(array $areas): array
    {
        $now = Carbon::parse('2026-01-13 08:00:00');
        $creatorId = User::first()?->id;

        $data = [
            ['area' => 0, 'code' => 'HGD-0001', 'head_name' => 'Trần Văn Bình', 'head_id' => '048075000201', 'address' => '15 Trần Phú, Hải Châu, Đà Nẵng', 'phone' => '0905111001'],
            ['area' => 0, 'code' => 'HGD-0002', 'head_name' => 'Nguyễn Thị Kim Cúc', 'head_id' => '048078000202', 'address' => '47 Lê Duẩn, Hải Châu, Đà Nẵng', 'phone' => '0905111002'],
            ['area' => 1, 'code' => 'HGD-0003', 'head_name' => 'Phạm Văn Đảo', 'head_id' => '048080000203', 'address' => '22 Ông Ích Khiêm, Hải Châu, Đà Nẵng', 'phone' => '0905111003'],
            ['area' => 1, 'code' => 'HGD-0004', 'head_name' => 'Đỗ Thị Gấm', 'head_id' => '048082000204', 'address' => '8 Hùng Vương, Hải Châu, Đà Nẵng', 'phone' => '0905111004'],
            ['area' => 2, 'code' => 'HGD-0005', 'head_name' => 'Lê Văn Hòa', 'head_id' => '048083000205', 'address' => '63 Yên Bái, Hải Châu, Đà Nẵng', 'phone' => '0905111005'],
            ['area' => 2, 'code' => 'HGD-0006', 'head_name' => 'Trương Thị Kim', 'head_id' => '048085000206', 'address' => '19 Phan Châu Trinh, Hải Châu, Đà Nẵng', 'phone' => '0905111006'],
            ['area' => 3, 'code' => 'HGD-0007', 'head_name' => 'Võ Văn Lợi', 'head_id' => '048087000207', 'address' => '5 Nguyễn Chí Thanh, Thanh Khê, Đà Nẵng', 'phone' => '0905111007'],
            ['area' => 3, 'code' => 'HGD-0008', 'head_name' => 'Huỳnh Thị Muội', 'head_id' => '048088000208', 'address' => '30 Điện Biên Phủ, Thanh Khê, Đà Nẵng', 'phone' => '0905111008'],
            ['area' => 4, 'code' => 'HGD-0009', 'head_name' => 'Ngô Văn Sáu', 'head_id' => '048089000209', 'address' => '12 Nguyễn Văn Linh, Thanh Khê, Đà Nẵng', 'phone' => '0905111009'],
            ['area' => 4, 'code' => 'HGD-0010', 'head_name' => 'Bùi Thị Bảy', 'head_id' => '048090000210', 'address' => '27 Điện Biên Phủ, Thanh Khê, Đà Nẵng', 'phone' => '0905111010'],
        ];

        $households = [];
        foreach ($data as $i => $row) {
            $household = Household::unguarded(fn () => Household::withoutGlobalScopes()->firstOrCreate(
                ['household_code' => $row['code'], 'organization_id' => self::ORG_ID],
                [
                    'residential_area_id' => $areas[$row['area']]->id,
                    'head_name' => $row['head_name'],
                    'head_id_number' => $row['head_id'],
                    'address' => $row['address'],
                    'phone' => $row['phone'],
                ]
            ));
            $this->stamp('beneficiary_households', $household->id, $now->copy()->addMinutes($i * 5), $creatorId);
            $households[] = $household;
        }

        return $households;
    }

    /** 14 người có công — phủ đủ 12 nhóm đối tượng theo Pháp lệnh 02/2020/UBTVQH14 + đa dạng trạng thái. */
    protected function seedBeneficiaries(array $households): array
    {
        $now = Carbon::parse('2026-01-15 09:00:00');
        $creatorId = User::first()?->id;

        $data = [
            ['household' => 0, 'id_number' => '048025000301', 'name' => 'Nguyễn Văn An', 'dob' => '1925-03-10', 'birth_year' => null, 'gender' => GenderEnum::Male, 'injury_rate' => null, 'status' => BeneficiaryStatusEnum::Active, 'decision_no' => '1234-QĐ/TU', 'recognition_date' => '2021-03-15', 'note' => 'Cán bộ lão thành cách mạng, hiện sống cùng con cháu.'],
            ['household' => 1, 'id_number' => '048028000302', 'name' => 'Lê Thị Sương', 'dob' => '1928-11-02', 'birth_year' => null, 'gender' => GenderEnum::Female, 'injury_rate' => null, 'status' => BeneficiaryStatusEnum::Active, 'decision_no' => '1235-QĐ/TU', 'recognition_date' => '2021-03-15', 'note' => null],
            ['household' => 2, 'id_number' => '048050000303', 'name' => 'Phạm Văn Chiến', 'dob' => null, 'birth_year' => '1950', 'gender' => GenderEnum::Male, 'injury_rate' => null, 'status' => BeneficiaryStatusEnum::Deceased, 'decision_no' => '45/QĐ-TTg', 'recognition_date' => '1975-06-02', 'death_date' => '1972-04-30', 'note' => 'Hy sinh tại mặt trận Quảng Trị năm 1972. Hồ sơ liệt sĩ, thân nhân đang hưởng trợ cấp thờ cúng.'],
            ['household' => 3, 'id_number' => '048030000304', 'name' => 'Đặng Thị Gái', 'dob' => '1930-01-05', 'birth_year' => null, 'gender' => GenderEnum::Female, 'injury_rate' => null, 'status' => BeneficiaryStatusEnum::Active, 'decision_no' => '678/QĐ-CTN', 'recognition_date' => '2014-07-20', 'note' => 'Mẹ Việt Nam anh hùng, có 2 con là liệt sĩ.'],
            ['household' => 4, 'id_number' => '048048000305', 'name' => 'Trần Văn Dũng', 'dob' => '1948-05-20', 'birth_year' => null, 'gender' => GenderEnum::Male, 'injury_rate' => null, 'status' => BeneficiaryStatusEnum::Active, 'decision_no' => '89/QĐ-CTN', 'recognition_date' => '1985-08-30', 'note' => null],
            ['household' => 5, 'id_number' => '048045000306', 'name' => 'Hồ Văn Kiên', 'dob' => '1945-09-15', 'birth_year' => null, 'gender' => GenderEnum::Male, 'injury_rate' => null, 'status' => BeneficiaryStatusEnum::Active, 'decision_no' => '112/QĐ-CTN', 'recognition_date' => '1990-01-10', 'note' => null],
            ['household' => 6, 'id_number' => '048050000307', 'name' => 'Nguyễn Văn Thương', 'dob' => '1950-06-01', 'birth_year' => null, 'gender' => GenderEnum::Male, 'injury_rate' => 61, 'status' => BeneficiaryStatusEnum::Active, 'decision_no' => '2201/QĐ-LĐTBXH', 'recognition_date' => '1980-04-12', 'note' => null],
            ['household' => 7, 'id_number' => '048052000308', 'name' => 'Lâm Văn Phong', 'dob' => '1952-02-18', 'birth_year' => null, 'gender' => GenderEnum::Male, 'injury_rate' => 41, 'status' => BeneficiaryStatusEnum::Active, 'decision_no' => '2202/QĐ-LĐTBXH', 'recognition_date' => '1982-05-20', 'note' => null],
            ['household' => 8, 'id_number' => '048055000309', 'name' => 'Trịnh Thị Hoa', 'dob' => '1955-07-07', 'birth_year' => null, 'gender' => GenderEnum::Female, 'injury_rate' => 61, 'status' => BeneficiaryStatusEnum::Active, 'decision_no' => '3301/QĐ-LĐTBXH', 'recognition_date' => '1995-09-01', 'note' => 'Bị nhiễm chất độc hóa học trong thời gian tham gia kháng chiến tại Quảng Trị.'],
            ['household' => 9, 'id_number' => '048058000310', 'name' => 'Ngô Văn Tài', 'dob' => '1958-03-25', 'birth_year' => null, 'gender' => GenderEnum::Male, 'injury_rate' => null, 'status' => BeneficiaryStatusEnum::Active, 'decision_no' => '4401/QĐ-LĐTBXH', 'recognition_date' => '1998-02-14', 'note' => 'Bị địch bắt tù đày tại nhà lao Côn Đảo giai đoạn 1970-1973.'],
            ['household' => 0, 'id_number' => '048060000311', 'name' => 'Đinh Văn Sơn', 'dob' => '1960-10-10', 'birth_year' => null, 'gender' => GenderEnum::Male, 'injury_rate' => null, 'status' => BeneficiaryStatusEnum::Pending, 'decision_no' => null, 'recognition_date' => null, 'note' => 'Đang hoàn thiện hồ sơ đề nghị công nhận người hoạt động kháng chiến, chờ Sở LĐTBXH thẩm định.'],
            ['household' => 1, 'id_number' => '048062000312', 'name' => 'Vương Thị Nga', 'dob' => '1962-12-05', 'birth_year' => null, 'gender' => GenderEnum::Female, 'injury_rate' => null, 'status' => BeneficiaryStatusEnum::Suspended, 'decision_no' => '5501/QĐ-LĐTBXH', 'recognition_date' => '2000-03-08', 'note' => 'Tạm dừng chi trả trợ cấp từ 01/2026 để xác minh lại nơi cư trú theo yêu cầu thanh tra.'],
            ['household' => 9, 'id_number' => '048065000313', 'name' => 'Cao Văn Hải', 'dob' => '1965-04-09', 'birth_year' => null, 'gender' => GenderEnum::Male, 'injury_rate' => 81, 'status' => BeneficiaryStatusEnum::MovedOut, 'decision_no' => '2203/QĐ-LĐTBXH', 'recognition_date' => '1983-01-01', 'note' => 'Đã chuyển hộ khẩu sang địa phương khác từ 06/2025, đang chờ bàn giao hồ sơ.'],
            ['household' => 3, 'id_number' => '048068000314', 'name' => 'Phan Thị Kim Anh', 'dob' => '1968-08-08', 'birth_year' => null, 'gender' => GenderEnum::Female, 'injury_rate' => 41, 'status' => BeneficiaryStatusEnum::Active, 'decision_no' => '2204/QĐ-LĐTBXH', 'recognition_date' => '1988-03-03', 'note' => 'Vừa là thương binh vừa là người hoạt động kháng chiến, hưởng đồng thời 2 chế độ theo quy định.'],
        ];

        $beneficiaries = [];
        foreach ($data as $i => $row) {
            $household = $households[$row['household']];
            $beneficiary = Beneficiary::unguarded(fn () => Beneficiary::withoutGlobalScopes()->firstOrCreate(
                ['id_number' => $row['id_number'], 'organization_id' => self::ORG_ID],
                [
                    'household_id' => $household->id,
                    'full_name' => $row['name'],
                    'date_of_birth' => $row['dob'],
                    'birth_year' => $row['birth_year'],
                    'gender' => $row['gender']->value,
                    'injury_rate' => $row['injury_rate'],
                    'recognition_decision_no' => $row['decision_no'],
                    'recognition_date' => $row['recognition_date'],
                    'status' => $row['status']->value,
                    'death_date' => $row['death_date'] ?? null,
                    'address' => $household->address,
                    'note' => $row['note'],
                ]
            ));
            $this->stamp('beneficiaries', $beneficiary->id, $now->copy()->addMinutes($i * 10), $creatorId);
            $beneficiaries[] = $beneficiary;
        }

        return $beneficiaries;
    }

    /** Phân loại đối tượng — mỗi người 1 phân loại chính, riêng người #13 hưởng đồng thời 2 chế độ. */
    protected function seedClassifications(array $beneficiaries): void
    {
        $sldtbxh = 'Sở Lao động - Thương binh và Xã hội TP Đà Nẵng';

        $data = [
            ['beneficiary' => 0, 'type' => BeneficiaryTypeEnum::PreRevolution1945, 'decision_no' => '1234-QĐ/TU', 'decision_date' => '2021-03-15', 'issued_by' => 'Ban Thường vụ Thành ủy Đà Nẵng', 'primary' => true],
            ['beneficiary' => 1, 'type' => BeneficiaryTypeEnum::Revolution1945To1945Uprising, 'decision_no' => '1235-QĐ/TU', 'decision_date' => '2021-03-15', 'issued_by' => 'Ban Thường vụ Thành ủy Đà Nẵng', 'primary' => true],
            ['beneficiary' => 2, 'type' => BeneficiaryTypeEnum::Martyr, 'decision_no' => '45/QĐ-TTg', 'decision_date' => '1975-06-02', 'issued_by' => 'Thủ tướng Chính phủ', 'primary' => true],
            ['beneficiary' => 3, 'type' => BeneficiaryTypeEnum::VietnameseHeroicMother, 'decision_no' => '678/QĐ-CTN', 'decision_date' => '2014-07-20', 'issued_by' => 'Chủ tịch nước', 'primary' => true],
            ['beneficiary' => 4, 'type' => BeneficiaryTypeEnum::HeroOfArmedForces, 'decision_no' => '89/QĐ-CTN', 'decision_date' => '1985-08-30', 'issued_by' => 'Chủ tịch nước', 'primary' => true],
            ['beneficiary' => 5, 'type' => BeneficiaryTypeEnum::HeroOfLabor, 'decision_no' => '112/QĐ-CTN', 'decision_date' => '1990-01-10', 'issued_by' => 'Chủ tịch nước', 'primary' => true],
            ['beneficiary' => 6, 'type' => BeneficiaryTypeEnum::WarInvalid, 'decision_no' => '2201/QĐ-LĐTBXH', 'decision_date' => '1980-04-12', 'issued_by' => $sldtbxh, 'primary' => true],
            ['beneficiary' => 7, 'type' => BeneficiaryTypeEnum::DiseaseInvalid, 'decision_no' => '2202/QĐ-LĐTBXH', 'decision_date' => '1982-05-20', 'issued_by' => $sldtbxh, 'primary' => true],
            ['beneficiary' => 8, 'type' => BeneficiaryTypeEnum::AgentOrangeVictim, 'decision_no' => '3301/QĐ-LĐTBXH', 'decision_date' => '1995-09-01', 'issued_by' => $sldtbxh, 'primary' => true],
            ['beneficiary' => 9, 'type' => BeneficiaryTypeEnum::FormerPrisoner, 'decision_no' => '4401/QĐ-LĐTBXH', 'decision_date' => '1998-02-14', 'issued_by' => $sldtbxh, 'primary' => true],
            ['beneficiary' => 10, 'type' => BeneficiaryTypeEnum::ResistanceActivist, 'decision_no' => null, 'decision_date' => null, 'issued_by' => null, 'primary' => true],
            ['beneficiary' => 11, 'type' => BeneficiaryTypeEnum::RevolutionSupporter, 'decision_no' => '5501/QĐ-LĐTBXH', 'decision_date' => '2000-03-08', 'issued_by' => $sldtbxh, 'primary' => true],
            ['beneficiary' => 12, 'type' => BeneficiaryTypeEnum::WarInvalid, 'decision_no' => '2203/QĐ-LĐTBXH', 'decision_date' => '1983-01-01', 'issued_by' => $sldtbxh, 'primary' => true],
            ['beneficiary' => 13, 'type' => BeneficiaryTypeEnum::WarInvalid, 'decision_no' => '2204/QĐ-LĐTBXH', 'decision_date' => '1988-03-03', 'issued_by' => $sldtbxh, 'primary' => true],
            ['beneficiary' => 13, 'type' => BeneficiaryTypeEnum::ResistanceActivist, 'decision_no' => '2205/QĐ-LĐTBXH', 'decision_date' => '1988-03-03', 'issued_by' => $sldtbxh, 'primary' => false],
        ];

        foreach ($data as $row) {
            $beneficiary = $beneficiaries[$row['beneficiary']];
            BeneficiaryClassification::unguarded(fn () => BeneficiaryClassification::firstOrCreate(
                ['beneficiary_id' => $beneficiary->id, 'type' => $row['type']->value],
                [
                    'decision_no' => $row['decision_no'],
                    'decision_date' => $row['decision_date'],
                    'issued_by' => $row['issued_by'],
                    'is_primary' => $row['primary'],
                ]
            ));
        }
    }

    /** 9 thân nhân — phủ đủ 3 tình trạng đủ điều kiện hưởng + còn sống/đã mất. */
    protected function seedDependents(array $households): array
    {
        $now = Carbon::parse('2026-02-01 09:00:00');
        $creatorId = User::first()?->id;

        $data = [
            ['household' => 6, 'id_number' => '048053000401', 'name' => 'Trần Thị Kim Oanh', 'dob' => '1953-04-01', 'gender' => GenderEnum::Female, 'is_alive' => true, 'eligibility' => DependentEligibilityEnum::Normal],
            ['household' => 6, 'id_number' => '048008000402', 'name' => 'Nguyễn Văn Bảo', 'dob' => '2008-09-01', 'gender' => GenderEnum::Male, 'is_alive' => true, 'eligibility' => DependentEligibilityEnum::Studying],
            ['household' => 6, 'id_number' => '048005000403', 'name' => 'Nguyễn Thị Bích', 'dob' => '2005-05-05', 'gender' => GenderEnum::Female, 'is_alive' => true, 'eligibility' => DependentEligibilityEnum::Normal],
            ['household' => 7, 'id_number' => '048085000404', 'name' => 'Lâm Thị Út', 'dob' => '1985-03-03', 'gender' => GenderEnum::Female, 'is_alive' => true, 'eligibility' => DependentEligibilityEnum::DisabledNoWorkCapacity],
            ['household' => 2, 'id_number' => '048055000405', 'name' => 'Phạm Văn Được', 'dob' => '1955-01-01', 'gender' => GenderEnum::Male, 'is_alive' => true, 'eligibility' => DependentEligibilityEnum::Normal],
            ['household' => 9, 'id_number' => '048042000406', 'name' => 'Nguyễn Thị Bảy', 'dob' => '1942-06-06', 'gender' => GenderEnum::Female, 'is_alive' => true, 'eligibility' => DependentEligibilityEnum::Normal],
            ['household' => 3, 'id_number' => null, 'name' => 'Đỗ Văn Toàn', 'dob' => '1940-01-01', 'gender' => GenderEnum::Male, 'is_alive' => false, 'death_date' => '2015-08-08', 'eligibility' => DependentEligibilityEnum::Normal],
            ['household' => 9, 'id_number' => '048068000407', 'name' => 'Bùi Thị Kim Hồng', 'dob' => '1968-11-11', 'gender' => GenderEnum::Female, 'is_alive' => true, 'eligibility' => DependentEligibilityEnum::Normal],
            ['household' => 3, 'id_number' => '048038000408', 'name' => 'Trịnh Văn Nuôi', 'dob' => '1938-02-02', 'gender' => GenderEnum::Male, 'is_alive' => true, 'eligibility' => DependentEligibilityEnum::Normal],
        ];

        $dependents = [];
        foreach ($data as $i => $row) {
            $household = $households[$row['household']];
            $dependent = Dependent::unguarded(fn () => Dependent::withoutGlobalScopes()->firstOrCreate(
                ['full_name' => $row['name'], 'household_id' => $household->id, 'organization_id' => self::ORG_ID],
                [
                    'date_of_birth' => $row['dob'],
                    'gender' => $row['gender']->value,
                    'id_number' => $row['id_number'],
                    'is_alive' => $row['is_alive'],
                    'death_date' => $row['death_date'] ?? null,
                    'eligibility_status' => $row['eligibility']->value,
                ]
            ));
            $this->stamp('beneficiary_dependents', $dependent->id, $now->copy()->addMinutes($i * 10), $creatorId);
            $dependents[] = $dependent;
        }

        return $dependents;
    }

    /** Quan hệ người có công - thân nhân — phủ đủ 6 loại quan hệ + 3 trạng thái hưởng. */
    protected function seedDependentRelations(array $beneficiaries, array $dependents): void
    {
        $data = [
            ['dependent' => 0, 'beneficiary' => 6, 'type' => DependentRelationshipEnum::Spouse, 'from' => '1980-04-12', 'until' => null, 'status' => DependentRelationStatusEnum::Active, 'note' => null],
            ['dependent' => 1, 'beneficiary' => 6, 'type' => DependentRelationshipEnum::Child, 'from' => '2008-09-01', 'until' => null, 'status' => DependentRelationStatusEnum::Active, 'note' => 'Đang học THPT, tiếp tục hưởng trợ cấp đến khi đủ 18 tuổi hoặc tốt nghiệp.'],
            ['dependent' => 2, 'beneficiary' => 6, 'type' => DependentRelationshipEnum::Child, 'from' => '2005-05-05', 'until' => '2023-05-05', 'status' => DependentRelationStatusEnum::Expired, 'note' => 'Đã đủ 18 tuổi và không tiếp tục đi học, hết điều kiện hưởng.'],
            ['dependent' => 3, 'beneficiary' => 7, 'type' => DependentRelationshipEnum::Child, 'from' => '1982-05-20', 'until' => null, 'status' => DependentRelationStatusEnum::Active, 'note' => 'Mất khả năng lao động, hưởng trợ cấp không thời hạn.'],
            ['dependent' => 4, 'beneficiary' => 2, 'type' => DependentRelationshipEnum::Guardian, 'from' => '1975-06-02', 'until' => null, 'status' => DependentRelationStatusEnum::Active, 'note' => 'Người thờ cúng liệt sĩ.'],
            ['dependent' => 5, 'beneficiary' => 12, 'type' => DependentRelationshipEnum::Mother, 'from' => '1983-01-01', 'until' => null, 'status' => DependentRelationStatusEnum::Suspended, 'note' => 'Tạm dừng theo hồ sơ chuyển đi của con.'],
            ['dependent' => 6, 'beneficiary' => 13, 'type' => DependentRelationshipEnum::Father, 'from' => '1988-03-03', 'until' => '2015-08-08', 'status' => DependentRelationStatusEnum::Expired, 'note' => 'Đã mất, ngừng hưởng chế độ thân nhân.'],
            ['dependent' => 7, 'beneficiary' => 12, 'type' => DependentRelationshipEnum::Spouse, 'from' => '1983-01-01', 'until' => null, 'status' => DependentRelationStatusEnum::Suspended, 'note' => 'Tạm dừng theo hồ sơ chuyển đi của chồng.'],
            ['dependent' => 8, 'beneficiary' => 3, 'type' => DependentRelationshipEnum::FosterParent, 'from' => '2014-07-20', 'until' => null, 'status' => DependentRelationStatusEnum::Active, 'note' => 'Người trực tiếp nuôi dưỡng, chăm sóc Mẹ Việt Nam anh hùng.'],
        ];

        foreach ($data as $row) {
            BeneficiaryDependentRelation::firstOrCreate(
                ['beneficiary_id' => $beneficiaries[$row['beneficiary']]->id, 'dependent_id' => $dependents[$row['dependent']]->id],
                [
                    'relationship_type' => $row['type']->value,
                    'eligible_from' => $row['from'],
                    'eligible_until' => $row['until'],
                    'status' => $row['status']->value,
                    'note' => $row['note'],
                ]
            );
        }
    }

    /** Trợ cấp đã cấp — cả 2 loại chủ thể (người có công / thân nhân) và đủ 3 trạng thái. */
    protected function seedSubsidyGrants(array $beneficiaries, array $dependents, array $policies): void
    {
        $now = Carbon::parse('2026-02-10 09:00:00');
        $creatorId = User::first()?->id;
        $beneficiaryMorph = (new Beneficiary())->getMorphClass();
        $dependentMorph = (new Dependent())->getMorphClass();

        $data = [
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[0], 'policy' => 'pre_revolution_1945', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[1], 'policy' => 'revolution_1945_to_1945_uprising', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $dependentMorph, 'subject' => $dependents[4], 'policy' => 'martyr', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[3], 'policy' => 'vietnamese_heroic_mother', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[4], 'policy' => 'hero_of_armed_forces', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[5], 'policy' => 'hero_of_labor', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[6], 'policy' => 'war_invalid', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[7], 'policy' => 'disease_invalid', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[8], 'policy' => 'agent_orange_victim', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[9], 'policy' => 'former_prisoner', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[11], 'policy' => 'revolution_supporter', 'to' => null, 'status' => SubsidyStatusEnum::Suspended, 'reason' => null],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[12], 'policy' => 'war_invalid', 'to' => '2025-06-30', 'status' => SubsidyStatusEnum::Terminated, 'reason' => 'Đối tượng chuyển đi khỏi địa phương'],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[13], 'policy' => 'war_invalid', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
            ['subject_type' => $dependentMorph, 'subject' => $dependents[1], 'policy' => 'dependent_child_care', 'to' => null, 'status' => SubsidyStatusEnum::Active, 'reason' => null],
        ];

        foreach ($data as $i => $row) {
            $policy = $policies[$row['policy']];
            $grant = SubsidyGrant::unguarded(fn () => SubsidyGrant::withoutGlobalScopes()->firstOrCreate(
                [
                    'subject_type' => $row['subject_type'],
                    'subject_id' => $row['subject']->id,
                    'beneficiary_subsidy_policy_id' => $policy->id,
                    'organization_id' => self::ORG_ID,
                ],
                [
                    'amount' => $policy->amount,
                    'granted_from' => '2021-07-01',
                    'granted_to' => $row['to'],
                    'status' => $row['status']->value,
                    'termination_reason' => $row['reason'],
                ]
            ));
            $this->stamp('beneficiary_subsidy_grants', $grant->id, $now->copy()->addMinutes($i * 10), $creatorId);
        }
    }

    /** Lịch sử đổi trạng thái — minh họa các mốc chuyển trạng thái thường gặp. */
    protected function seedStatusHistories(array $beneficiaries): void
    {
        $staff = User::whereIn('user_name', ['ttmai', 'lhnam', 'htlan'])->get()->keyBy('user_name');

        $data = [
            ['beneficiary' => 0, 'old' => 'pending', 'new' => 'active', 'reason' => 'Cán bộ xác nhận đầy đủ hồ sơ, cập nhật trạng thái đang hưởng trợ cấp.', 'by' => 'htlan', 'at' => '2026-02-01 10:00:00'],
            ['beneficiary' => 6, 'old' => 'pending', 'new' => 'active', 'reason' => 'Hoàn tất thẩm định hồ sơ thương binh, đủ điều kiện hưởng trợ cấp hàng tháng.', 'by' => 'ttmai', 'at' => '2026-01-20 09:30:00'],
            ['beneficiary' => 11, 'old' => 'active', 'new' => 'suspended', 'reason' => 'Tạm dừng chi trả để xác minh nơi cư trú theo yêu cầu Thanh tra Sở Lao động - Thương binh và Xã hội.', 'by' => 'lhnam', 'at' => '2026-01-15 14:00:00'],
            ['beneficiary' => 12, 'old' => 'active', 'new' => 'moved_out', 'reason' => 'Đối tượng chuyển hộ khẩu ra ngoài địa bàn quản lý.', 'by' => 'ttmai', 'at' => '2025-06-20 08:45:00'],
        ];

        foreach ($data as $row) {
            $changedAt = Carbon::parse($row['at']);
            $beneficiary = $beneficiaries[$row['beneficiary']];

            $history = StatusHistory::unguarded(fn () => StatusHistory::withoutGlobalScopes()->firstOrCreate(
                [
                    'subject_type' => $beneficiary::class,
                    'subject_id' => $beneficiary->id,
                    'new_status' => $row['new'],
                    'organization_id' => self::ORG_ID,
                ],
                [
                    'old_status' => $row['old'],
                    'reason' => $row['reason'],
                    'changed_by' => $staff[$row['by']]->id,
                    'changed_at' => $changedAt,
                ]
            ));

            DB::table('beneficiary_status_histories')->where('id', $history->id)->update([
                'created_at' => $changedAt, 'updated_at' => $changedAt,
            ]);
        }
    }

    /** Lịch thăm hỏi — phủ đủ 4 dịp, 3 trạng thái, cả 3 loại chủ thể (hộ / người có công / thân nhân). */
    protected function seedVisitSchedules(array $beneficiaries, array $households, array $dependents): void
    {
        $now = Carbon::parse('2026-01-20 10:00:00');
        $creatorId = User::first()?->id;
        $staff = User::whereIn('user_name', ['ttmai', 'lhnam', 'htlan', 'dmtuan', 'btngoc', 'htduong'])->get()->keyBy('user_name');

        $beneficiaryMorph = (new Beneficiary())->getMorphClass();
        $householdMorph = (new Household())->getMorphClass();
        $dependentMorph = (new Dependent())->getMorphClass();

        $data = [
            ['subject_type' => $householdMorph, 'subject' => $households[0], 'occasion' => VisitOccasionEnum::Tet, 'date' => '2026-02-16', 'status' => ScheduleStatusEnum::Done, 'by' => 'ttmai', 'note' => 'Đã trao quà Tết 2.000.000đ/hộ và thăm hỏi sức khỏe cụ Nguyễn Văn An.'],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[6], 'occasion' => VisitOccasionEnum::WarInvalidsDay, 'date' => '2026-07-27', 'status' => ScheduleStatusEnum::Pending, 'by' => 'lhnam', 'note' => 'Thăm hỏi, tặng quà nhân kỷ niệm Ngày Thương binh - Liệt sĩ 27/7.'],
            ['subject_type' => $dependentMorph, 'subject' => $dependents[4], 'occasion' => VisitOccasionEnum::WarInvalidsDay, 'date' => '2026-07-27', 'status' => ScheduleStatusEnum::Pending, 'by' => 'htlan', 'note' => 'Thăm hỏi, viếng gia đình liệt sĩ nhân 27/7.'],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[3], 'occasion' => VisitOccasionEnum::Birthday, 'date' => '2026-09-10', 'status' => ScheduleStatusEnum::Pending, 'by' => 'dmtuan', 'note' => 'Chúc thọ, thăm hỏi nhân dịp sinh nhật Mẹ Việt Nam anh hùng.'],
            ['subject_type' => $householdMorph, 'subject' => $households[6], 'occasion' => VisitOccasionEnum::Custom, 'date' => '2026-06-01', 'status' => ScheduleStatusEnum::Done, 'by' => 'btngoc', 'note' => 'Tặng quà Ngày Quốc tế Thiếu nhi cho con thương binh đang đi học.'],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[11], 'occasion' => VisitOccasionEnum::Tet, 'date' => '2026-02-14', 'status' => ScheduleStatusEnum::Skipped, 'by' => 'lhnam', 'note' => 'Không thực hiện được do đối tượng đang tạm dừng chế độ, chưa xác minh được nơi cư trú mới.'],
            ['subject_type' => $beneficiaryMorph, 'subject' => $beneficiaries[8], 'occasion' => VisitOccasionEnum::WarInvalidsDay, 'date' => '2026-07-27', 'status' => ScheduleStatusEnum::Pending, 'by' => 'htduong', 'note' => 'Thăm hỏi nạn nhân chất độc da cam nhân 27/7.'],
        ];

        foreach ($data as $i => $row) {
            $schedule = VisitSchedule::unguarded(fn () => VisitSchedule::withoutGlobalScopes()->firstOrCreate(
                [
                    'subject_type' => $row['subject_type'],
                    'subject_id' => $row['subject']->id,
                    'occasion' => $row['occasion']->value,
                    'scheduled_date' => $row['date'],
                    'organization_id' => self::ORG_ID,
                ],
                [
                    'status' => $row['status']->value,
                    'assigned_to' => $staff[$row['by']]->id,
                    'note' => $row['note'],
                ]
            ));
            $this->stamp('beneficiary_visit_schedules', $schedule->id, $now->copy()->addMinutes($i * 15), $creatorId);
        }
    }

    /** Ghi đè created_at/updated_at/created_by/updated_by để dữ liệu demo có mốc thời gian hợp lý. */
    protected function stamp(string $table, int $id, Carbon $at, ?int $userId): void
    {
        DB::table($table)->where('id', $id)->update([
            'created_by' => $userId, 'updated_by' => $userId,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }
}
