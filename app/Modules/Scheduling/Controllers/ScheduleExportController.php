<?php

namespace App\Modules\Scheduling\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\Scheduling\Exports\WeeklyScheduleExcelExport;
use App\Modules\Scheduling\Exports\WeeklySchedulePdfExporter;
use App\Modules\Scheduling\Exports\WeeklyScheduleWordExporter;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @group Scheduling - Xuất báo cáo lịch công tác
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Hỗ trợ xuất ma trận lịch công tác tuần dưới các định dạng Excel, PDF, và Word.
 */
class ScheduleExportController extends Controller
{
    use AuthorizesRequests;

    /**
     * Xuất lịch tuần ra Excel.
     *
     * @queryParam week_number integer Số thứ tự tuần trong năm. Example: 23
     * @queryParam year integer Năm cần xuất lịch. Example: 2026
     * @queryParam module_type string Phân hệ cần lọc (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam status integer Lọc trạng thái (0: Nháp, 1: Chờ duyệt, 2: Đã duyệt/Công bố, 3: Đã hủy). Example: 2
     */
    public function excel(FilterRequest $request)
    {
        $this->authorize('export', Schedule::class);

        $filters = $request->all();
        if (empty($filters['week_number'])) {
            $filters['week_number'] = (int) date('W');
        }
        if (empty($filters['year'])) {
            $filters['year'] = (int) date('Y');
        }

        return Excel::download(
            new WeeklyScheduleExcelExport($filters),
            ExportFilename::make('lich-cong-tac-tuan', 'xlsx')
        );
    }

    /**
     * Xuất lịch tuần ra PDF.
     *
     * @queryParam week_number integer Số thứ tự tuần trong năm. Example: 23
     * @queryParam year integer Năm cần xuất lịch. Example: 2026
     * @queryParam module_type string Phân hệ cần lọc (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam status integer Lọc trạng thái (0: Nháp, 1: Chờ duyệt, 2: Đã duyệt/Công bố, 3: Đã hủy). Example: 2
     */
    public function pdf(FilterRequest $request)
    {
        $this->authorize('export', Schedule::class);

        $filters = $request->all();
        if (empty($filters['week_number'])) {
            $filters['week_number'] = (int) date('W');
        }
        if (empty($filters['year'])) {
            $filters['year'] = (int) date('Y');
        }

        $exporter = new WeeklySchedulePdfExporter();
        $tempPath = $exporter->generate($filters);

        return response()->download(
            $tempPath,
            ExportFilename::make('lich-cong-tac-tuan', 'pdf')
        )->deleteFileAfterSend();
    }

    /**
     * Xuất lịch tuần ra Word.
     *
     * @queryParam week_number integer Số thứ tự tuần trong năm. Example: 23
     * @queryParam year integer Năm cần xuất lịch. Example: 2026
     * @queryParam module_type string Phân hệ cần lọc (EXECUTIVE, OFFICE). Example: EXECUTIVE
     * @queryParam status integer Lọc trạng thái (0: Nháp, 1: Chờ duyệt, 2: Đã duyệt/Công bố, 3: Đã hủy). Example: 2
     */
    public function word(FilterRequest $request)
    {
        $this->authorize('export', Schedule::class);

        $filters = $request->all();
        if (empty($filters['week_number'])) {
            $filters['week_number'] = (int) date('W');
        }
        if (empty($filters['year'])) {
            $filters['year'] = (int) date('Y');
        }

        $exporter = new WeeklyScheduleWordExporter();
        $tempPath = $exporter->generate($filters);

        return response()->download(
            $tempPath,
            ExportFilename::make('lich-cong-tac-tuan', 'docx')
        )->deleteFileAfterSend();
    }
}
