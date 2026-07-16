<?php

namespace Database\Seeders;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryClassification;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\ResidentialArea;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use App\Modules\Core\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed danh mục chính sách trợ cấp (catalog toàn TP, organization_id = NULL) + dữ liệu demo
 * (tổ dân phố, hộ gia đình, người có công) cho tenant mặc định để FE có dữ liệu test.
 * Mức trợ cấp là số liệu THAM KHẢO — cần đối chiếu số liệu thật với Sở LĐTBXH trước khi dùng thật.
 */
class BeneficiaryDataSeeder extends Seeder
{
    public function run(): void
    {
        auth()->setUser(User::first());

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(1);
        }

        $this->seedSubsidyPolicies();
        $this->seedResidentialAreasAndDemo();

        auth()->forgetUser();
    }

    protected function seedSubsidyPolicies(): void
    {
        $now = Carbon::parse('2021-07-01 00:00:00');

        $policies = [
            ['beneficiary_type' => BeneficiaryTypeEnum::WarInvalid->value, 'amount' => 3500000, 'legal_basis' => 'Nghị định 75/2021/NĐ-CP'],
            ['beneficiary_type' => BeneficiaryTypeEnum::DiseaseInvalid->value, 'amount' => 3200000, 'legal_basis' => 'Nghị định 75/2021/NĐ-CP'],
            ['beneficiary_type' => BeneficiaryTypeEnum::AgentOrangeVictim->value, 'amount' => 2800000, 'legal_basis' => 'Nghị định 75/2021/NĐ-CP'],
            ['beneficiary_type' => BeneficiaryTypeEnum::VietnameseHeroicMother->value, 'amount' => 4800000, 'legal_basis' => 'Nghị định 75/2021/NĐ-CP'],
            ['beneficiary_type' => BeneficiaryTypeEnum::ResistanceActivist->value, 'amount' => 2100000, 'legal_basis' => 'Nghị định 75/2021/NĐ-CP'],
        ];

        foreach ($policies as $policy) {
            $record = SubsidyPolicy::unguarded(fn () => SubsidyPolicy::withoutGlobalScopes()->firstOrCreate(
                ['beneficiary_type' => $policy['beneficiary_type'], 'organization_id' => null, 'effective_from' => $now],
                ['amount' => $policy['amount'], 'unit' => 'VND/tháng', 'legal_basis' => $policy['legal_basis']]
            ));

            DB::table('beneficiary_subsidy_policies')->where('id', $record->id)->update([
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    protected function seedResidentialAreasAndDemo(): void
    {
        $now = Carbon::parse('2026-01-10 08:00:00');
        $creatorId = User::first()?->id;

        $area = ResidentialArea::unguarded(fn () => ResidentialArea::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'Tổ 5', 'organization_id' => 1],
            ['code' => 'TDP-005']
        ));
        DB::table('beneficiary_residential_areas')->where('id', $area->id)->update([
            'created_by' => $creatorId, 'updated_by' => $creatorId,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $household = Household::unguarded(fn () => Household::withoutGlobalScopes()->firstOrCreate(
            ['household_code' => 'DEMO-HGD-00001', 'organization_id' => 1],
            [
                'residential_area_id' => $area->id,
                'head_name' => 'Nguyễn Văn Demo',
                'address' => '12 Trần Phú, Hải Châu, Đà Nẵng',
                'phone' => '0905000001',
            ]
        ));
        DB::table('beneficiary_households')->where('id', $household->id)->update([
            'created_by' => $creatorId, 'updated_by' => $creatorId,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $beneficiary = Beneficiary::unguarded(fn () => Beneficiary::withoutGlobalScopes()->firstOrCreate(
            ['id_number' => '049000000001', 'organization_id' => 1],
            [
                'household_id' => $household->id,
                'full_name' => 'Nguyễn Văn Demo',
                'date_of_birth' => '1950-05-20',
                'gender' => GenderEnum::Male->value,
                'injury_rate' => 61,
                'recognition_decision_no' => 'QD-DEMO-001',
                'recognition_date' => '2021-01-01',
                'status' => BeneficiaryStatusEnum::Active->value,
                'address' => '12 Trần Phú, Hải Châu, Đà Nẵng',
            ]
        ));
        DB::table('beneficiaries')->where('id', $beneficiary->id)->update([
            'created_by' => $creatorId, 'updated_by' => $creatorId,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        BeneficiaryClassification::unguarded(fn () => BeneficiaryClassification::firstOrCreate(
            ['beneficiary_id' => $beneficiary->id, 'type' => BeneficiaryTypeEnum::WarInvalid->value],
            [
                'decision_no' => 'QD-DEMO-001',
                'decision_date' => '2021-01-01',
                'issued_by' => 'Sở Lao động - Thương binh và Xã hội TP Đà Nẵng',
                'is_primary' => true,
            ]
        ));
    }
}
