<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\LogActivity;
use App\Modules\Core\Requests\BulkDestroyLogActivityRequest;
use App\Modules\Core\Requests\DestroyByDateLogActivityRequest;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Resources\LogActivityCollection;
use App\Modules\Core\Resources\LogActivityResource;
use App\Modules\Core\Services\LogActivityService;

/**
 * @group Core - LogActivity
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý nhật ký truy cập: thống kê, danh sách, chi tiết, xuất Excel, xóa, xóa hàng loạt, xóa theo thời gian, xóa toàn bộ.
 */
class LogActivityController extends Controller
{
    public function __construct(private LogActivityService $logActivityService) {}

    /**
     * Thống kê nhật ký
     *
     * Tổng số bản ghi sau khi áp dụng bộ lọc.
     *
     * @queryParam search string Tìm kiếm (description, route, ip_address, country, user_type). Example: 127.0.0.1
     * @queryParam organization_id integer Lọc theo tổ chức. Example: 1
     * @queryParam from_date date Lọc từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày (Y-m-d). Example: 2026-12-31
     * @queryParam method_type string GET, POST, PUT, PATCH, DELETE. Example: GET
     * @queryParam status_code integer Mã HTTP (200, 400, 500...). Example: 200
     * @queryParam sort_by string id, description, route, method_type, status_code, ip_address, country, created_at. Example: created_at
     * @queryParam sort_order string asc, desc. Example: desc
     * @queryParam limit integer 1-100. Example: 10
     *
     * @response 200 {"success": true, "data": {"total": 100}}
     */
    public function stats(FilterRequest $request)
    {
        return $this->success($this->logActivityService->stats($request->all()));
    }

    /**
     * Timeline thống kê nhật ký (line chart dashboard)
     *
     * Stat ALL dữ liệu, group theo grain FE chọn (tương ứng dropdown "theo tháng" / "theo ngày").
     *
     * @queryParam granularity string `day` hoặc `month`. Mặc định `month`. Example: month
     *
     * @response 200 {"success":true,"data":{"granularity":"month","data":[{"period":"2026-01","total":2100,"views":1800,"creates":200,"updates":80,"deletes":20}]}}
     */
    public function timeline(FilterRequest $request)
    {
        $granularity = (string) $request->input('granularity', 'month');

        return $this->success($this->logActivityService->timeline($granularity));
    }

    /**
     * Top N người dùng hoạt động nhiều nhất
     *
     * @queryParam limit integer Số bản ghi (mặc định 5). Example: 5
     * @queryParam from_date date Từ ngày. Example: 2026-01-01
     * @queryParam to_date date Đến ngày. Example: 2026-12-31
     * @queryParam organization_id integer Lọc theo tổ chức.
     *
     * @response 200 {"success":true,"data":[{"user_id":1,"name":"Nguyễn Văn A","email":"...","user_name":"nva","total":2448}]}
     */
    public function topUsers(FilterRequest $request)
    {
        $limit = (int) $request->input('limit', 5);

        return $this->success($this->logActivityService->topUsers($request->all(), $limit));
    }

    /**
     * Top N tổ chức hoạt động nhiều nhất
     *
     * @queryParam limit integer Số bản ghi (mặc định 5). Example: 5
     * @queryParam from_date date Từ ngày. Example: 2026-01-01
     * @queryParam to_date date Đến ngày. Example: 2026-12-31
     *
     * @response 200 {"success":true,"data":[{"organization_id":1,"name":"Sở Nội vụ","slug":"so-noi-vu","total":2448}]}
     */
    public function topOrganizations(FilterRequest $request)
    {
        $limit = (int) $request->input('limit', 5);

        return $this->success($this->logActivityService->topOrganizations($request->all(), $limit));
    }

    /**
     * Snapshot dashboard tổng hợp
     *
     * Trả về stats + timeline + top users + top organizations trong 1 request,
     * tránh sai lệch số liệu do middleware tự log mỗi request làm tăng count giữa các lời gọi.
     *
     * @queryParam search string Từ khóa tìm kiếm. Example: login
     * @queryParam organization_id integer Lọc theo tổ chức. Example: 1
     * @queryParam from_date date Từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Đến ngày (Y-m-d). Example: 2026-12-31
     * @queryParam method_type string GET, POST, PUT, PATCH, DELETE. Example: GET
     * @queryParam status_code integer Mã HTTP. Example: 200
     * @queryParam granularity string `day` hoặc `month` (mặc định `month`). Example: month
     * @queryParam top_users_limit integer 1-100, mặc định 5. Example: 5
     * @queryParam top_organizations_limit integer 1-100, mặc định 5. Example: 5
     *
     * @response 200 {"success":true,"data":{"stats":{"total":2107,"views":2089,"creates":14,"updates":4,"deletes":0},"timeline":{"granularity":"month","data":[{"period":"2026-04","total":2107,"views":2089,"creates":14,"updates":4,"deletes":0}]},"top_users":[{"user_id":1,"name":"Admin","email":"...","user_name":"admin","total":2097}],"top_organizations":[{"organization_id":1,"name":"Sở Nội vụ","slug":"so-noi-vu","total":2107}]}}
     */
    public function dashboard(FilterRequest $request)
    {
        $granularity = (string) $request->input('granularity', 'month');
        $topUsersLimit = (int) $request->input('top_users_limit', 5);
        $topOrganizationsLimit = (int) $request->input('top_organizations_limit', 5);

        return $this->success($this->logActivityService->dashboard(
            $request->all(),
            $topUsersLimit,
            $topOrganizationsLimit,
            $granularity,
        ));
    }

    /**
     * Danh sách nhật ký
     *
     * @queryParam search string Tìm kiếm. Example: login
     * @queryParam organization_id integer Lọc theo tổ chức. Example: 1
     * @queryParam from_date date Từ ngày. Example: 2026-01-01
     * @queryParam to_date date Đến ngày. Example: 2026-12-31
     * @queryParam method_type string GET, POST, PUT, PATCH, DELETE.
     * @queryParam status_code integer Mã HTTP.
     * @queryParam sort_by string Example: created_at
     * @queryParam sort_order string asc, desc. Example: desc
     * @queryParam limit integer Example: 10
     *
     * @apiResourceCollection App\Modules\Core\Resources\LogActivityCollection
     *
     * @apiResourceModel App\Modules\Core\Models\LogActivity paginate=10
     *
     * @apiResourceAdditional success=true
     */
    public function index(FilterRequest $request)
    {
        $logs = $this->logActivityService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new LogActivityCollection($logs));
    }

    /**
     * Xuất danh sách nhật ký
     *
     * Áp dụng cùng bộ lọc với index. Trả về file Excel.
     *
     * @queryParam search string Tìm kiếm (description, route, ip_address, country, user_type).
     * @queryParam organization_id integer Lọc theo tổ chức. Example: 1
     * @queryParam from_date date Lọc từ ngày (Y-m-d). Example: 2026-01-01
     * @queryParam to_date date Lọc đến ngày (Y-m-d). Example: 2026-12-31
     * @queryParam method_type string GET, POST, PUT, PATCH, DELETE. Example: GET
     * @queryParam status_code integer Mã HTTP (200, 400, 500...). Example: 200
     * @queryParam sort_by string id, description, route, method_type, status_code, ip_address, country, created_at.
     * @queryParam sort_order string asc, desc. Example: desc
     *
     * Xuất ra các trường: id, description, user_type, user_id, user_name, organization_id, route, method_type, status_code, ip_address, country, user_agent, request_data, created_at, updated_at.
     */
    public function export(FilterRequest $request)
    {
        return $this->logActivityService->export($request->all());
    }

    /**
     * Chi tiết nhật ký
     *
     * @urlParam logActivity integer required ID nhật ký. Example: 1
     *
     * @apiResource App\Modules\Core\Resources\LogActivityResource
     *
     * @apiResourceModel App\Modules\Core\Models\LogActivity with=user,organization
     *
     * @apiResourceAdditional success=true
     */
    public function show(LogActivity $logActivity)
    {
        $logActivity = $this->logActivityService->show($logActivity);

        return $this->successResource(new LogActivityResource($logActivity));
    }

    /**
     * Xóa nhật ký
     *
     * @urlParam logActivity integer required ID nhật ký. Example: 1
     *
     * @response 200 {"success": true, "message": "Đã xóa nhật ký thành công!"}
     */
    public function destroy(LogActivity $logActivity)
    {
        $this->logActivityService->destroy($logActivity);

        return $this->success(null, 'Đã xóa nhật ký thành công!');
    }

    /**
     * Xóa hàng loạt nhật ký
     *
     * @bodyParam ids array required Danh sách ID. Example: [1, 2, 3]
     *
     * @response 200 {"success": true, "message": "Đã xóa thành công 3 nhật ký!"}
     */
    public function bulkDestroy(BulkDestroyLogActivityRequest $request)
    {
        $count = $this->logActivityService->bulkDestroy($request->ids);

        return $this->success(null, "Đã xóa thành công {$count} nhật ký!");
    }

    /**
     * Xóa nhật ký theo khoảng thời gian
     *
     * @bodyParam from_date date required Từ ngày (Y-m-d). Example: 2026-01-01
     * @bodyParam to_date date required Đến ngày (Y-m-d). Example: 2026-01-31
     *
     * @response 200 {"success": true, "message": "Đã xóa thành công 10 nhật ký trong khoảng thời gian!"}
     */
    public function destroyByDate(DestroyByDateLogActivityRequest $request)
    {
        $count = $this->logActivityService->destroyByDate($request->from_date, $request->to_date);

        return $this->success(null, "Đã xóa thành công {$count} nhật ký trong khoảng thời gian!");
    }

    /**
     * Xóa toàn bộ nhật ký
     *
     * @response 200 {"success": true, "message": "Đã xóa toàn bộ 100 nhật ký!"}
     */
    public function destroyAll()
    {
        $count = $this->logActivityService->destroyAll();

        return $this->success(null, "Đã xóa toàn bộ {$count} nhật ký!");
    }
}
