<?php

namespace App\Modules\Beneficiary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Beneficiary\Services\StatisticsService;
use Illuminate\Http\Request;

/**
 * @group Beneficiary - Thống kê (Dashboard)
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Số liệu tổng hợp phục vụ trang dashboard người có công (KPI + các biểu đồ theo loại đối tượng,
 * trạng thái, tổ dân phố, giới tính, nhóm tuổi, quan hệ thân nhân, tiếp nhận mới theo tháng).
 * Mỗi breakdown trả mảng `{ key, label, total }` để FE dựng thẳng bar/pie/line.
 */
class StatisticsController extends Controller
{
    public function __construct(private StatisticsService $service) {}

    /**
     * Tổng hợp toàn bộ số liệu dashboard (1 lần load)
     *
     * @queryParam year integer Năm cho biểu đồ tiếp nhận mới theo tháng (mặc định năm hiện tại). Example: 2026
     *
     * @response 200 {"success": true, "data": {"summary": {"total_beneficiaries": 14, "active_beneficiaries": 10, "total_dependents": 9, "total_households": 10, "total_residential_areas": 5, "total_documents": 5}, "by_type": [{"key": "martyr", "label": "Liệt sĩ", "total": 1}], "by_status": [{"key": "active", "label": "Đang hưởng", "total": 10}], "by_residential_area": [{"key": 1, "label": "Tổ 1", "total": 3}], "by_gender": [{"key": "male", "label": "Nam", "total": 9}], "by_age_group": [{"key": "90_plus", "label": "90 trở lên", "total": 2}], "by_relationship": [{"key": "child", "label": "Con", "total": 3}], "new_by_month": {"year": 2026, "data": [{"key": 1, "label": "Tháng 1", "total": 0}]}}}
     */
    public function overview(Request $request)
    {
        return $this->success($this->service->overview($this->year($request)));
    }

    /**
     * Số người có công theo loại đối tượng (12 nhóm)
     *
     * @response 200 {"success": true, "data": [{"key": "war_invalid", "label": "Thương binh, người hưởng chính sách như thương binh", "total": 2}]}
     */
    public function byType()
    {
        return $this->success($this->service->byType());
    }

    /**
     * Số người có công theo trạng thái
     *
     * @response 200 {"success": true, "data": [{"key": "active", "label": "Đang hưởng", "total": 10}]}
     */
    public function byStatus()
    {
        return $this->success($this->service->byStatus());
    }

    /**
     * Số người có công theo tổ dân phố / thôn
     *
     * @response 200 {"success": true, "data": [{"key": 1, "label": "Tổ 1", "total": 3}]}
     */
    public function byResidentialArea()
    {
        return $this->success($this->service->byResidentialArea());
    }

    /**
     * Số hộ gia đình theo tổ dân phố / thôn
     *
     * @response 200 {"success": true, "data": [{"key": 1, "label": "Tổ 1", "total": 2}]}
     */
    public function householdsByArea()
    {
        return $this->success($this->service->householdsByArea());
    }

    /**
     * Số người có công theo giới tính
     *
     * @response 200 {"success": true, "data": [{"key": "male", "label": "Nam", "total": 9}]}
     */
    public function byGender()
    {
        return $this->success($this->service->byGender());
    }

    /**
     * Số người có công theo nhóm tuổi
     *
     * @response 200 {"success": true, "data": [{"key": "80_89", "label": "80 - 89", "total": 3}]}
     */
    public function byAgeGroup()
    {
        return $this->success($this->service->byAgeGroup());
    }

    /**
     * Số thân nhân theo loại quan hệ
     *
     * @response 200 {"success": true, "data": [{"key": "child", "label": "Con", "total": 3}]}
     */
    public function byRelationship()
    {
        return $this->success($this->service->byRelationship());
    }

    /**
     * Số người có công tiếp nhận mới theo tháng
     *
     * @queryParam year integer Năm thống kê (mặc định năm hiện tại). Example: 2026
     *
     * @response 200 {"success": true, "data": {"year": 2026, "data": [{"key": 1, "label": "Tháng 1", "total": 2}]}}
     */
    public function newByMonth(Request $request)
    {
        return $this->success($this->service->newByMonth($this->year($request)));
    }

    private function year(Request $request): ?int
    {
        $year = $request->query('year');

        return is_numeric($year) ? (int) $year : null;
    }
}
