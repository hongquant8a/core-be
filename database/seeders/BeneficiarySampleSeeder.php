<?php

namespace Database\Seeders;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
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

/**
 * Seed dữ liệu MẪU số lượng lớn cho module Người có công — mỗi danh sách 100 bản ghi,
 * dùng factory + gắn quan hệ đầy đủ (hộ ↔ tổ dân phố, NCC ↔ hộ + phân loại + giấy tờ,
 * thân nhân ↔ hộ + tổ dân phố + quan hệ với NCC). `created_at` của NCC được trải đều
 * theo tháng trong năm hiện tại để biểu đồ dashboard "tiếp nhận mới theo tháng" có dữ liệu.
 *
 * Chạy: `sail artisan db:seed --class=BeneficiarySampleSeeder`
 * (đã đăng ký trong DatabaseSeeder nên `migrate:fresh --seed` cũng tạo).
 */
class BeneficiarySampleSeeder extends Seeder
{
    private const ORG_ID = 1;

    private const N = 100;

    public function run(): void
    {
        auth()->setUser(User::first());

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(self::ORG_ID);
        }

        $year = Carbon::now()->year;
        $types = BeneficiaryTypeEnum::values();
        $statuses = BeneficiaryStatusEnum::values();
        $relationships = DependentRelationshipEnum::values();

        // 1) Tổ dân phố (100)
        $areaIds = ResidentialArea::factory()->count(self::N)->create()->pluck('id');

        // 2) Hộ gia đình (100) — mỗi hộ gắn 1 tổ dân phố, CCCD chủ hộ duy nhất.
        $householdIds = collect(range(1, self::N))->map(fn () => Household::factory()->create([
            'residential_area_id' => $areaIds->random(),
            'head_id_number' => fake()->unique()->numerify('07#########'),
        ])->id);

        // 3) Người có công (100) — gắn hộ + tổ dân phố ngẫu nhiên, trải created_at theo tháng trong năm.
        $beneficiaries = collect(range(1, self::N))->map(function ($i) use ($householdIds, $areaIds, $statuses, $year) {
            $date = Carbon::create($year, random_int(1, 12), random_int(1, 28), random_int(7, 17));

            return Beneficiary::factory()->create([
                'household_id' => $householdIds->random(),
                'residential_area_id' => $areaIds->random(),
                'status' => $statuses[array_rand($statuses)],
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        });
        $beneficiaryIds = $beneficiaries->pluck('id');

        // 4) Phân loại đối tượng (100) — 1 phân loại chính / người có công.
        foreach ($beneficiaries as $b) {
            BeneficiaryClassification::factory()->create([
                'beneficiary_id' => $b->id,
                'type' => $types[array_rand($types)],
                'is_primary' => true,
            ]);
        }

        // 5) Thân nhân (100) — gắn hộ + tổ dân phố ngẫu nhiên.
        $dependents = collect(range(1, self::N))->map(fn () => Dependent::factory()->create([
            'household_id' => $householdIds->random(),
            'residential_area_id' => $areaIds->random(),
        ]));

        // 6) Quan hệ NCC–thân nhân (100 cặp duy nhất: thân nhân[i] ↔ người có công[i]).
        foreach ($dependents as $i => $d) {
            BeneficiaryDependentRelation::factory()->create([
                'beneficiary_id' => $beneficiaries[$i]->id,
                'dependent_id' => $d->id,
                'relationship_type' => $relationships[array_rand($relationships)],
            ]);
        }

        // 7) Giấy tờ hồ sơ (100) — gắn 1 người có công ngẫu nhiên (chưa gắn tập tin thực).
        $docNames = [
            'Quyết định công nhận', 'Giấy chứng nhận thương binh', 'Bản sao CCCD',
            'Giấy khai sinh', 'Kết luận giám định', 'Hồ sơ liệt sĩ', 'Sổ hưởng trợ cấp',
        ];
        collect(range(1, self::N))->each(fn () => BeneficiaryDocument::factory()->create([
            'beneficiary_id' => $beneficiaryIds->random(),
            'name' => $docNames[array_rand($docNames)],
        ]));

        $this->command?->info('BeneficiarySampleSeeder: đã tạo 100 bản ghi cho mỗi danh sách của module Người có công.');
    }
}
