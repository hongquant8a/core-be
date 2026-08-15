<?php

namespace Database\Seeders;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryRelationship;
use App\Modules\Beneficiary\Models\BeneficiaryResidentialArea;
use App\Modules\Beneficiary\Models\BeneficiaryType;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu khởi tạo cho module Người có công.
 *
 * Ba danh mục seed theo nghiệp vụ thật (Pháp lệnh ưu đãi người có công) chứ không phải dữ
 * liệu giả — cán bộ dùng được ngay mà không phải tự gõ lại. Hồ sơ mẫu chỉ vài dòng để kiểm
 * thử luồng, không seed hàng loạt.
 *
 * Chạy với `withoutGlobalScope` không cần thiết: seeder gán `organization_id` tường minh.
 */
class BeneficiaryDataSeeder extends Seeder
{
    private const ORG_ID = 1;

    public function run(): void
    {
        $this->seedResidentialAreas();
        $this->seedTypes();
        $this->seedRelationships();
        $this->seedSampleBeneficiaries();
    }

    private function seedResidentialAreas(): void
    {
        foreach (range(1, 12) as $i) {
            BeneficiaryResidentialArea::withoutGlobalScope('organization')->firstOrCreate(
                ['organization_id' => self::ORG_ID, 'name' => "Tổ dân phố {$i}"],
                [
                    'sort_order' => $i,
                    'status' => CatalogStatusEnum::Active->value,
                    'created_by' => 1,
                    'updated_by' => 1,
                ]
            );
        }
    }

    private function seedTypes(): void
    {
        $types = [
            'Người hoạt động cách mạng trước 01/01/1945',
            'Liệt sĩ',
            'Bà mẹ Việt Nam anh hùng',
            'Anh hùng Lực lượng vũ trang nhân dân',
            'Thương binh',
            'Bệnh binh',
            'Người hoạt động kháng chiến bị nhiễm chất độc hoá học',
            'Người hoạt động cách mạng bị địch bắt tù, đày',
            'Người có công giúp đỡ cách mạng',
            'Thân nhân liệt sĩ',
        ];

        foreach ($types as $i => $name) {
            BeneficiaryType::withoutGlobalScope('organization')->firstOrCreate(
                ['organization_id' => self::ORG_ID, 'name' => $name],
                [
                    'sort_order' => $i + 1,
                    'status' => CatalogStatusEnum::Active->value,
                    'created_by' => 1,
                    'updated_by' => 1,
                ]
            );
        }
    }

    private function seedRelationships(): void
    {
        $relationships = ['Vợ', 'Chồng', 'Con', 'Bố', 'Mẹ', 'Anh', 'Chị', 'Em', 'Cháu', 'Khác'];

        foreach ($relationships as $i => $name) {
            BeneficiaryRelationship::withoutGlobalScope('organization')->firstOrCreate(
                ['organization_id' => self::ORG_ID, 'name' => $name],
                [
                    'sort_order' => $i + 1,
                    'status' => CatalogStatusEnum::Active->value,
                    'created_by' => 1,
                    'updated_by' => 1,
                ]
            );
        }
    }

    /**
     * Ba hồ sơ mẫu, mỗi hồ sơ có đủ ba danh sách con — vừa đủ để kiểm thử save-full, export
     * và bộ lọc mà không làm nặng DB dev.
     */
    private function seedSampleBeneficiaries(): void
    {
        $areas = BeneficiaryResidentialArea::withoutGlobalScope('organization')
            ->where('organization_id', self::ORG_ID)->pluck('id')->all();
        $types = BeneficiaryType::withoutGlobalScope('organization')
            ->where('organization_id', self::ORG_ID)->pluck('id', 'name')->all();
        $relationships = BeneficiaryRelationship::withoutGlobalScope('organization')
            ->where('organization_id', self::ORG_ID)->pluck('id', 'name')->all();

        $samples = [
            [
                'full_name' => 'Nguyễn Văn Bảy',
                'birth_date' => '1948-05-19',
                'gender' => GenderEnum::Male->value,
                'id_number' => '048048000001',
                'phone' => '0905000001',
                'address' => '25 Lê Duẩn, Hải Châu',
                'latitude' => 16.0712,
                'longitude' => 108.2214,
                'types' => ['Thương binh' => true, 'Người hoạt động cách mạng bị địch bắt tù, đày' => false],
                'dependents' => [
                    ['full_name' => 'Trần Thị Lan', 'relationship' => 'Vợ', 'is_primary' => true, 'gender' => GenderEnum::Female->value],
                    ['full_name' => 'Nguyễn Văn Hùng', 'relationship' => 'Con', 'is_primary' => false, 'gender' => GenderEnum::Male->value],
                ],
                'documents' => ['Quyết định trợ cấp thương binh', 'Giấy chứng nhận thương binh'],
            ],
            [
                'full_name' => 'Lê Thị Sáu',
                'birth_date' => '1935-11-02',
                'gender' => GenderEnum::Female->value,
                'id_number' => '048035000002',
                'phone' => '0905000002',
                'address' => '10 Nguyễn Văn Linh, Thanh Khê',
                'latitude' => 16.0605,
                'longitude' => 108.2101,
                'types' => ['Bà mẹ Việt Nam anh hùng' => true, 'Thân nhân liệt sĩ' => false],
                'dependents' => [
                    ['full_name' => 'Lê Văn Nam', 'relationship' => 'Con', 'is_primary' => true, 'gender' => GenderEnum::Male->value],
                ],
                'documents' => ['Quyết định phong tặng danh hiệu'],
            ],
            [
                'full_name' => 'Phạm Minh Đức',
                // Chỉ biết năm sinh — ca dùng birth_year mà không có birth_date.
                'birth_date' => null,
                'birth_year' => 1952,
                'gender' => GenderEnum::Male->value,
                'id_number' => '048052000003',
                'phone' => null,
                'address' => 'Tổ 3, Hoà Vang',
                'latitude' => null,
                'longitude' => null,
                'types' => ['Bệnh binh' => true],
                'dependents' => [],
                'documents' => [],
            ],
        ];

        foreach ($samples as $i => $sample) {
            $beneficiary = Beneficiary::withoutGlobalScope('organization')->firstOrCreate(
                ['organization_id' => self::ORG_ID, 'id_number' => $sample['id_number']],
                [
                    'full_name' => $sample['full_name'],
                    'birth_date' => $sample['birth_date'],
                    'birth_year' => $sample['birth_year'] ?? null,
                    'gender' => $sample['gender'],
                    'phone' => $sample['phone'],
                    'residential_area_id' => $areas[$i % count($areas)] ?? null,
                    'address' => $sample['address'],
                    'latitude' => $sample['latitude'],
                    'longitude' => $sample['longitude'],
                    'created_by' => 1,
                    'updated_by' => 1,
                ]
            );

            foreach ($sample['types'] as $typeName => $isPrimary) {
                if (! isset($types[$typeName])) {
                    continue;
                }

                $beneficiary->typeRelations()->withoutGlobalScope('organization')->firstOrCreate(
                    ['beneficiary_type_id' => $types[$typeName]],
                    [
                        'organization_id' => self::ORG_ID,
                        'is_primary' => $isPrimary,
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]
                );
            }

            foreach ($sample['dependents'] as $dependent) {
                $beneficiary->dependents()->withoutGlobalScope('organization')->firstOrCreate(
                    ['full_name' => $dependent['full_name']],
                    [
                        'organization_id' => self::ORG_ID,
                        'gender' => $dependent['gender'],
                        'relationship_id' => $relationships[$dependent['relationship']] ?? null,
                        'is_primary' => $dependent['is_primary'],
                        'created_by' => 1,
                        'updated_by' => 1,
                    ]
                );
            }

            foreach ($sample['documents'] as $documentName) {
                $beneficiary->documents()->withoutGlobalScope('organization')->firstOrCreate(
                    ['name' => $documentName],
                    ['organization_id' => self::ORG_ID, 'created_by' => 1, 'updated_by' => 1]
                );
            }
        }
    }
}
