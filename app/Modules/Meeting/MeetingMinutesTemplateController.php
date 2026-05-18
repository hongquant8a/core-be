<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingMinutesTemplate;
use App\Modules\Meeting\Requests\StoreMeetingMinutesTemplateRequest;
use App\Modules\Meeting\Requests\UpdateMeetingMinutesTemplateRequest;
use App\Modules\Meeting\Resources\MeetingMinutesTemplateResource;
use App\Modules\Meeting\Services\MeetingMinutesGenerator;
use App\Modules\Meeting\Services\MeetingMinutesTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * @group Meeting - Template biên bản
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý template .docx dùng cho xuất biên bản họp. Template per module Meeting,
 * không scope theo organization (dùng chung).
 */
class MeetingMinutesTemplateController extends Controller
{
    public function __construct(
        private MeetingMinutesTemplateService $service,
        private MeetingMinutesGenerator $generator,
    ) {}

    /**
     * Cheatsheet biến template — public docs cho người soạn .docx.
     *
     * Trả về danh sách biến scalar + biến table FE hiển thị để người dùng biết chèn vào file.
     *
     * @response 200 {"success": true, "data": {"scalar": {...}, "tables": {...}}}
     */
    public function variables()
    {
        return $this->success([
            'scalar' => MeetingMinutesGenerator::SCALAR_VARIABLES,
            'tables' => MeetingMinutesGenerator::TABLE_VARIABLES,
        ]);
    }

    /**
     * Tải file template mẫu (.docx) — chứa toàn bộ placeholder + cấu trúc biên bản HĐND chuẩn.
     *
     * Auth-only, không Spatie permission. User download về làm starting point khi soạn
     * template thật (mở Word -> chỉnh style/font -> upload qua endpoint store).
     */
    public function downloadSample()
    {
        $path = $this->generator->generateSample();

        return response()->download(
            $path,
            'meeting-minutes-template-sample.docx'
        )->deleteFileAfterSend(true);
    }

    /**
     * Danh sách template biên bản.
     *
     * @queryParam search string Tìm theo tên template. Example: HĐND
     * @queryParam status string active|inactive. Example: active
     * @queryParam sort_by string Sắp xếp theo: id, name, is_default, created_at, updated_at. Example: updated_at
     * @queryParam sort_order string asc|desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->service->index($request->all(), (int) ($request->limit ?? 10));

        return $this->success([
            'items' => MeetingMinutesTemplateResource::collection($items->getCollection()),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Chi tiết template.
     *
     * @urlParam meetingMinutesTemplate integer required ID template. Example: 1
     */
    public function show(MeetingMinutesTemplate $meetingMinutesTemplate)
    {
        return $this->successResource(new MeetingMinutesTemplateResource(
            $this->service->show($meetingMinutesTemplate)
        ));
    }

    /**
     * Danh sách template biên bản — dùng cho dialog "Chọn template" trước khi gọi
     * export-minutes. Gate `operate,meeting` (chair/op của meeting — pure FK, không Spatie).
     *
     * Reuse cùng service như index admin nhưng auth qua Gate Policy thay vì
     * permission `meeting-minutes-templates.index`.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @queryParam search string Tìm theo tên template. Example: HĐND
     * @queryParam status string active|inactive. Example: active
     * @queryParam sort_by string id, name, is_default, created_at, updated_at. Example: is_default
     * @queryParam sort_order string asc|desc. Example: desc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 100
     */
    public function indexInMeeting(Meeting $meeting, FilterRequest $request)
    {
        $items = $this->service->index($request->all(), (int) ($request->limit ?? 10));

        return $this->success([
            'items' => MeetingMinutesTemplateResource::collection($items->getCollection()),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Tạo template mới (upload file .docx).
     *
     * @bodyParam name string required Tên template. Example: Mẫu biên bản HĐND
     * @bodyParam description string Mô tả. Example: Mẫu chuẩn HĐND thường lệ.
     * @bodyParam file file required Tệp .docx có placeholder ${var_name}.
     * @bodyParam is_default boolean Đặt làm mặc định. Example: true
     * @bodyParam status string Trạng thái (active/inactive). Example: active
     */
    public function store(StoreMeetingMinutesTemplateRequest $request)
    {
        $item = $this->service->store($request->validated(), $request->file('file'));

        return $this->successResource(new MeetingMinutesTemplateResource($item), 'Tạo template thành công!', 201);
    }

    /**
     * Cập nhật template (có thể thay file).
     *
     * @urlParam meetingMinutesTemplate integer required ID template. Example: 1
     */
    public function update(UpdateMeetingMinutesTemplateRequest $request, MeetingMinutesTemplate $meetingMinutesTemplate)
    {
        $item = $this->service->update(
            $meetingMinutesTemplate,
            $request->validated(),
            $request->file('file'),
        );

        return $this->successResource(new MeetingMinutesTemplateResource($item), 'Cập nhật template thành công!');
    }

    /**
     * Xóa template.
     *
     * @urlParam meetingMinutesTemplate integer required ID template. Example: 1
     */
    public function destroy(MeetingMinutesTemplate $meetingMinutesTemplate)
    {
        $this->service->destroy($meetingMinutesTemplate);

        return $this->success(null, 'Xóa template thành công!');
    }

    /**
     * Xuất biên bản .docx cho 1 meeting cụ thể, dùng template chỉ định.
     *
     * Auth-only. Gate `operate` pure FK — chỉ chair/operator của meeting đó. Không Spatie bypass.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @bodyParam template_id integer required ID template biên bản. Example: 1
     */
    public function exportMinutes(Request $request, Meeting $meeting)
    {
        $request->validate([
            'template_id' => 'required|integer|exists:meeting_minutes_templates,id',
        ]);

        Gate::authorize('operate', $meeting);

        $template = MeetingMinutesTemplate::findOrFail((int) $request->input('template_id'));

        $path = $this->generator->generate($meeting, $template);

        // Slug tiếng Việt: bỏ dấu + lowercase + dash. Fallback theo ID nếu title rỗng.
        $slug = Str::slug((string) $meeting->title) ?: ('meeting-'.$meeting->id);
        $filename = ExportFilename::make('bien-ban-'.$slug, 'docx');

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }
}
