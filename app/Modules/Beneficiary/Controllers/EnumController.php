<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentEligibilityEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use App\Modules\Beneficiary\Enums\DependentRelationStatusEnum;
use App\Modules\Beneficiary\Enums\DocumentTypeEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Enums\ScheduleStatusEnum;
use App\Modules\Beneficiary\Enums\SubsidyStatusEnum;
use App\Modules\Beneficiary\Enums\VisitOccasionEnum;

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
     * @response 200 {"success": true, "data": {"beneficiary_type": [{"value": "martyr", "label": "Liệt sĩ"}], "beneficiary_status": [{"value": "active", "label": "Đang hưởng"}], "gender": [{"value": "male", "label": "Nam"}], "dependent_eligibility": [{"value": "normal", "label": "Bình thường"}], "dependent_relationship": [{"value": "spouse", "label": "Vợ/Chồng"}], "dependent_relation_status": [{"value": "active", "label": "Đang hưởng"}], "subsidy_status": [{"value": "active", "label": "Đang chi trả"}], "document_type": [{"value": "decision", "label": "Quyết định công nhận"}], "visit_occasion": [{"value": "tet", "label": "Tết Nguyên đán"}], "schedule_status": [{"value": "pending", "label": "Chờ thực hiện"}]}}
     */
    public function index()
    {
        return $this->success([
            'beneficiary_type' => $this->mapEnum(BeneficiaryTypeEnum::cases()),
            'beneficiary_status' => $this->mapEnum(BeneficiaryStatusEnum::cases()),
            'gender' => $this->mapEnum(GenderEnum::cases()),
            'dependent_eligibility' => $this->mapEnum(DependentEligibilityEnum::cases()),
            'dependent_relationship' => $this->mapEnum(DependentRelationshipEnum::cases()),
            'dependent_relation_status' => $this->mapEnum(DependentRelationStatusEnum::cases()),
            'subsidy_status' => $this->mapEnum(SubsidyStatusEnum::cases()),
            'document_type' => $this->mapEnum(DocumentTypeEnum::cases()),
            'visit_occasion' => $this->mapEnum(VisitOccasionEnum::cases()),
            'schedule_status' => $this->mapEnum(ScheduleStatusEnum::cases()),
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
