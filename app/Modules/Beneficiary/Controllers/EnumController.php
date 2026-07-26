<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;

/**
 * @group Beneficiary - Danh mục Enum
 *
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Tra cứu value/label của toàn bộ enum tĩnh dùng trong module Người có công,
 * để FE dựng dropdown thay vì hardcode.
 */
class EnumController extends Controller
{
    /**
     * Danh sách toàn bộ enum tĩnh của module Beneficiary
     *
     * @response 200 {"success": true, "data": {"beneficiary_type": [{"value": "martyr", "label": "Liệt sĩ"}], "beneficiary_status": [{"value": "active", "label": "Đang hưởng"}], "gender": [{"value": "male", "label": "Nam"}], "dependent_relationship": [{"value": "wife", "label": "Vợ"}]}}
     */
    public function index()
    {
        return $this->success([
            'beneficiary_type' => $this->mapEnum(BeneficiaryTypeEnum::cases()),
            'beneficiary_status' => $this->mapEnum(BeneficiaryStatusEnum::cases()),
            'gender' => $this->mapEnum(GenderEnum::cases()),
            'dependent_relationship' => $this->mapEnum(DependentRelationshipEnum::cases()),
        ]);
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     */
    private function mapEnum(array $cases): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            $cases
        );
    }
}
