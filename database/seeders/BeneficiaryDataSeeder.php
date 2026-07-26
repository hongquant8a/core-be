<?php

namespace Database\Seeders;

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
use App\Modules\Core\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed dữ liệu mẫu module Người có công (bản đơn giản hóa — chỉ thông tin cơ bản):
 * tổ dân phố, hộ gia đình, người có công (đủ 12 nhóm đối tượng), phân loại, thân nhân,
 * quan hệ, và một số giấy tờ hồ sơ. Phục vụ demo cho tenant mặc định.
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

        $areas = $this->seedResidentialAreas();
        $households = $this->seedHouseholds($areas);
        $beneficiaries = $this->seedBeneficiaries($households);
        $this->seedClassifications($beneficiaries);
        $dependents = $this->seedDependents($households);
        $this->seedDependentRelations($beneficiaries, $dependents);
        $this->seedDocuments($beneficiaries);

        auth()->forgetUser();
    }

    /** Danh mục tổ dân phố thuộc phường Hải Châu / Thanh Khê, TP Đà Nẵng. */
    protected function seedResidentialAreas(): array
    {
        $now = Carbon::parse('2026-01-12 08:00:00');
        $creatorId = User::first()?->id;

        $data = [
            ['name' => 'Tổ 1', 'note' => 'Khu vực ven đường Trần Phú'],
            ['name' => 'Tổ 2', 'note' => null],
            ['name' => 'Tổ 3', 'note' => null],
            ['name' => 'Tổ 4', 'note' => null],
            ['name' => 'Tổ 5', 'note' => 'Khu vực gần chợ Hàn'],
        ];

        $areas = [];
        foreach ($data as $i => $row) {
            $area = ResidentialArea::unguarded(fn () => ResidentialArea::withoutGlobalScopes()->firstOrCreate(
                ['name' => $row['name'], 'organization_id' => self::ORG_ID],
                ['note' => $row['note']]
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
            ['area' => 0, 'head_name' => 'Trần Văn Bình', 'head_id' => '048075000201', 'address' => '15 Trần Phú, Hải Châu, Đà Nẵng', 'phone' => '0905111001'],
            ['area' => 0, 'head_name' => 'Nguyễn Thị Kim Cúc', 'head_id' => '048078000202', 'address' => '47 Lê Duẩn, Hải Châu, Đà Nẵng', 'phone' => '0905111002'],
            ['area' => 1, 'head_name' => 'Phạm Văn Đảo', 'head_id' => '048080000203', 'address' => '22 Ông Ích Khiêm, Hải Châu, Đà Nẵng', 'phone' => '0905111003'],
            ['area' => 1, 'head_name' => 'Đỗ Thị Gấm', 'head_id' => '048082000204', 'address' => '8 Hùng Vương, Hải Châu, Đà Nẵng', 'phone' => '0905111004'],
            ['area' => 2, 'head_name' => 'Lê Văn Hòa', 'head_id' => '048083000205', 'address' => '63 Yên Bái, Hải Châu, Đà Nẵng', 'phone' => '0905111005'],
            ['area' => 2, 'head_name' => 'Trương Thị Kim', 'head_id' => '048085000206', 'address' => '19 Phan Châu Trinh, Hải Châu, Đà Nẵng', 'phone' => '0905111006'],
            ['area' => 3, 'head_name' => 'Võ Văn Lợi', 'head_id' => '048087000207', 'address' => '5 Nguyễn Chí Thanh, Thanh Khê, Đà Nẵng', 'phone' => '0905111007'],
            ['area' => 3, 'head_name' => 'Huỳnh Thị Muội', 'head_id' => '048088000208', 'address' => '30 Điện Biên Phủ, Thanh Khê, Đà Nẵng', 'phone' => '0905111008'],
            ['area' => 4, 'head_name' => 'Ngô Văn Sáu', 'head_id' => '048089000209', 'address' => '12 Nguyễn Văn Linh, Thanh Khê, Đà Nẵng', 'phone' => '0905111009'],
            ['area' => 4, 'head_name' => 'Bùi Thị Bảy', 'head_id' => '048090000210', 'address' => '27 Điện Biên Phủ, Thanh Khê, Đà Nẵng', 'phone' => '0905111010'],
        ];

        $households = [];
        foreach ($data as $i => $row) {
            $household = Household::unguarded(fn () => Household::withoutGlobalScopes()->firstOrCreate(
                ['head_id_number' => $row['head_id'], 'organization_id' => self::ORG_ID],
                [
                    'residential_area_id' => $areas[$row['area']]->id,
                    'head_name' => $row['head_name'],
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
            ['household' => 0, 'id_number' => '048025000301', 'name' => 'Nguyễn Văn An', 'dob' => '1925-03-10', 'birth_year' => null, 'gender' => GenderEnum::Male, 'status' => BeneficiaryStatusEnum::Active, 'note' => 'Cán bộ lão thành cách mạng, hiện sống cùng con cháu.'],
            ['household' => 1, 'id_number' => '048028000302', 'name' => 'Lê Thị Sương', 'dob' => '1928-11-02', 'birth_year' => null, 'gender' => GenderEnum::Female, 'status' => BeneficiaryStatusEnum::Active, 'note' => null],
            ['household' => 2, 'id_number' => '048050000303', 'name' => 'Phạm Văn Chiến', 'dob' => null, 'birth_year' => '1950', 'gender' => GenderEnum::Male, 'status' => BeneficiaryStatusEnum::Deceased, 'death_date' => '1972-04-30', 'note' => 'Hy sinh tại mặt trận Quảng Trị năm 1972. Hồ sơ liệt sĩ.'],
            ['household' => 3, 'id_number' => '048030000304', 'name' => 'Đặng Thị Gái', 'dob' => '1930-01-05', 'birth_year' => null, 'gender' => GenderEnum::Female, 'status' => BeneficiaryStatusEnum::Active, 'note' => 'Mẹ Việt Nam anh hùng, có 2 con là liệt sĩ.'],
            ['household' => 4, 'id_number' => '048048000305', 'name' => 'Trần Văn Dũng', 'dob' => '1948-05-20', 'birth_year' => null, 'gender' => GenderEnum::Male, 'status' => BeneficiaryStatusEnum::Active, 'note' => null],
            ['household' => 5, 'id_number' => '048045000306', 'name' => 'Hồ Văn Kiên', 'dob' => '1945-09-15', 'birth_year' => null, 'gender' => GenderEnum::Male, 'status' => BeneficiaryStatusEnum::Active, 'note' => null],
            ['household' => 6, 'id_number' => '048050000307', 'name' => 'Nguyễn Văn Thương', 'dob' => '1950-06-01', 'birth_year' => null, 'gender' => GenderEnum::Male, 'status' => BeneficiaryStatusEnum::Active, 'note' => null],
            ['household' => 7, 'id_number' => '048052000308', 'name' => 'Lâm Văn Phong', 'dob' => '1952-02-18', 'birth_year' => null, 'gender' => GenderEnum::Male, 'status' => BeneficiaryStatusEnum::Active, 'note' => null],
            ['household' => 8, 'id_number' => '048055000309', 'name' => 'Trịnh Thị Hoa', 'dob' => '1955-07-07', 'birth_year' => null, 'gender' => GenderEnum::Female, 'status' => BeneficiaryStatusEnum::Active, 'note' => 'Bị nhiễm chất độc hóa học trong thời gian tham gia kháng chiến.'],
            ['household' => 9, 'id_number' => '048058000310', 'name' => 'Ngô Văn Tài', 'dob' => '1958-03-25', 'birth_year' => null, 'gender' => GenderEnum::Male, 'status' => BeneficiaryStatusEnum::Active, 'note' => 'Bị địch bắt tù đày tại nhà lao Côn Đảo giai đoạn 1970-1973.'],
            ['household' => 0, 'id_number' => '048060000311', 'name' => 'Đinh Văn Sơn', 'dob' => '1960-10-10', 'birth_year' => null, 'gender' => GenderEnum::Male, 'status' => BeneficiaryStatusEnum::Pending, 'note' => 'Đang hoàn thiện hồ sơ đề nghị công nhận, chờ Sở LĐTBXH thẩm định.'],
            ['household' => 1, 'id_number' => '048062000312', 'name' => 'Vương Thị Nga', 'dob' => '1962-12-05', 'birth_year' => null, 'gender' => GenderEnum::Female, 'status' => BeneficiaryStatusEnum::Suspended, 'note' => 'Tạm dừng chi trả từ 01/2026 để xác minh lại nơi cư trú.'],
            ['household' => 9, 'id_number' => '048065000313', 'name' => 'Cao Văn Hải', 'dob' => '1965-04-09', 'birth_year' => null, 'gender' => GenderEnum::Male, 'status' => BeneficiaryStatusEnum::MovedOut, 'note' => 'Đã chuyển hộ khẩu sang địa phương khác từ 06/2025.'],
            ['household' => 3, 'id_number' => '048068000314', 'name' => 'Phan Thị Kim Anh', 'dob' => '1968-08-08', 'birth_year' => null, 'gender' => GenderEnum::Female, 'status' => BeneficiaryStatusEnum::Active, 'note' => 'Vừa là thương binh vừa là người hoạt động kháng chiến.'],
        ];

        $beneficiaries = [];
        foreach ($data as $i => $row) {
            $household = $households[$row['household']];
            $beneficiary = Beneficiary::unguarded(fn () => Beneficiary::withoutGlobalScopes()->firstOrCreate(
                ['id_number' => $row['id_number'], 'organization_id' => self::ORG_ID],
                [
                    'household_id' => $household->id,
                    // Tổ dân phố là trường riêng của người có công — dữ liệu mẫu lấy theo hộ,
                    // giống `address` ở dưới (thực tế cán bộ có thể sửa lệch với hộ).
                    'residential_area_id' => $household->residential_area_id,
                    'full_name' => $row['name'],
                    'date_of_birth' => $row['dob'],
                    'birth_year' => $row['birth_year'],
                    'gender' => $row['gender']->value,
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

    /** 9 thân nhân, gắn tổ dân phố theo hộ. */
    protected function seedDependents(array $households): array
    {
        $now = Carbon::parse('2026-02-01 09:00:00');
        $creatorId = User::first()?->id;

        $data = [
            ['household' => 6, 'id_number' => '048053000401', 'name' => 'Trần Thị Kim Oanh', 'dob' => '1953-04-01', 'gender' => GenderEnum::Female, 'phone' => '0905222001'],
            ['household' => 6, 'id_number' => '048008000402', 'name' => 'Nguyễn Văn Bảo', 'dob' => '2008-09-01', 'gender' => GenderEnum::Male, 'phone' => null],
            ['household' => 6, 'id_number' => '048005000403', 'name' => 'Nguyễn Thị Bích', 'dob' => '2005-05-05', 'gender' => GenderEnum::Female, 'phone' => null],
            ['household' => 7, 'id_number' => '048085000404', 'name' => 'Lâm Thị Út', 'dob' => '1985-03-03', 'gender' => GenderEnum::Female, 'phone' => '0905222004'],
            ['household' => 2, 'id_number' => '048055000405', 'name' => 'Phạm Văn Được', 'dob' => '1955-01-01', 'gender' => GenderEnum::Male, 'phone' => '0905222005'],
            ['household' => 9, 'id_number' => '048042000406', 'name' => 'Nguyễn Thị Bảy', 'dob' => '1942-06-06', 'gender' => GenderEnum::Female, 'phone' => null],
            ['household' => 3, 'id_number' => null, 'name' => 'Đỗ Văn Toàn', 'dob' => '1940-01-01', 'gender' => GenderEnum::Male, 'phone' => null],
            ['household' => 9, 'id_number' => '048068000407', 'name' => 'Bùi Thị Kim Hồng', 'dob' => '1968-11-11', 'gender' => GenderEnum::Female, 'phone' => '0905222008'],
            ['household' => 3, 'id_number' => '048038000408', 'name' => 'Trịnh Văn Nuôi', 'dob' => '1938-02-02', 'gender' => GenderEnum::Male, 'phone' => null],
        ];

        $dependents = [];
        foreach ($data as $i => $row) {
            $household = $households[$row['household']];
            $dependent = Dependent::unguarded(fn () => Dependent::withoutGlobalScopes()->firstOrCreate(
                ['full_name' => $row['name'], 'household_id' => $household->id, 'organization_id' => self::ORG_ID],
                [
                    'residential_area_id' => $household->residential_area_id,
                    'date_of_birth' => $row['dob'],
                    'gender' => $row['gender']->value,
                    'id_number' => $row['id_number'],
                    'phone' => $row['phone'],
                ]
            ));
            $this->stamp('beneficiary_dependents', $dependent->id, $now->copy()->addMinutes($i * 10), $creatorId);
            $dependents[] = $dependent;
        }

        return $dependents;
    }

    /** Quan hệ người có công - thân nhân — phủ đủ 6 loại quan hệ. */
    protected function seedDependentRelations(array $beneficiaries, array $dependents): void
    {
        $data = [
            ['dependent' => 0, 'beneficiary' => 6, 'type' => DependentRelationshipEnum::Spouse, 'note' => null],
            ['dependent' => 1, 'beneficiary' => 6, 'type' => DependentRelationshipEnum::Child, 'note' => 'Đang học THPT.'],
            ['dependent' => 2, 'beneficiary' => 6, 'type' => DependentRelationshipEnum::Child, 'note' => null],
            ['dependent' => 3, 'beneficiary' => 7, 'type' => DependentRelationshipEnum::Child, 'note' => 'Mất khả năng lao động.'],
            ['dependent' => 4, 'beneficiary' => 2, 'type' => DependentRelationshipEnum::Guardian, 'note' => 'Người thờ cúng liệt sĩ.'],
            ['dependent' => 5, 'beneficiary' => 12, 'type' => DependentRelationshipEnum::Mother, 'note' => null],
            ['dependent' => 6, 'beneficiary' => 13, 'type' => DependentRelationshipEnum::Father, 'note' => null],
            ['dependent' => 7, 'beneficiary' => 12, 'type' => DependentRelationshipEnum::Spouse, 'note' => null],
            ['dependent' => 8, 'beneficiary' => 3, 'type' => DependentRelationshipEnum::FosterParent, 'note' => 'Người trực tiếp nuôi dưỡng Mẹ Việt Nam anh hùng.'],
        ];

        foreach ($data as $row) {
            BeneficiaryDependentRelation::firstOrCreate(
                ['beneficiary_id' => $beneficiaries[$row['beneficiary']]->id, 'dependent_id' => $dependents[$row['dependent']]->id],
                ['relationship_type' => $row['type']->value, 'note' => $row['note']]
            );
        }
    }

    /** Một số giấy tờ hồ sơ mẫu (chưa gắn tập tin — file đính kèm qua API/MediaService khi vận hành). */
    protected function seedDocuments(array $beneficiaries): void
    {
        $now = Carbon::parse('2026-02-05 09:00:00');
        $creatorId = User::first()?->id;

        $data = [
            ['beneficiary' => 0, 'name' => 'Quyết định công nhận cán bộ lão thành cách mạng'],
            ['beneficiary' => 0, 'name' => 'Bản sao CCCD'],
            ['beneficiary' => 3, 'name' => 'Quyết định phong tặng Mẹ Việt Nam anh hùng'],
            ['beneficiary' => 6, 'name' => 'Giấy chứng nhận thương binh'],
            ['beneficiary' => 8, 'name' => 'Kết luận giám định nhiễm chất độc hóa học'],
        ];

        foreach ($data as $i => $row) {
            $document = BeneficiaryDocument::unguarded(fn () => BeneficiaryDocument::withoutGlobalScopes()->firstOrCreate(
                ['beneficiary_id' => $beneficiaries[$row['beneficiary']]->id, 'name' => $row['name'], 'organization_id' => self::ORG_ID],
                []
            ));
            $this->stamp('beneficiary_documents', $document->id, $now->copy()->addMinutes($i * 5), $creatorId);
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
