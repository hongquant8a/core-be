<?php

namespace App\Modules\Meeting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\Meeting\Exports\MeetingDocumentExport;
use App\Modules\Meeting\Exports\MeetingDocumentViewExport;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\ReorderMeetingDocumentRequest;
use App\Modules\Meeting\Requests\StoreMeetingDocumentRequest;
use App\Modules\Meeting\Requests\UpdateMeetingDocumentRequest;
use App\Modules\Meeting\Resources\MeetingDocumentCollection;
use App\Modules\Meeting\Resources\MeetingDocumentResource;
use App\Modules\Meeting\Services\MeetingDocumentService;
use Illuminate\Support\Facades\Gate;

/**
 * @group Meeting - Tài liệu họp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý tài liệu họp: danh sách, chi tiết, tạo, cập nhật, xóa và sắp xếp.
 */
class MeetingDocumentController extends Controller
{
    public function __construct(private MeetingDocumentService $meetingDocumentService) {}

    /**
     * Danh sách tài liệu họp công khai của 1 cuộc họp (Tab 2 Tài liệu — guest cũng xem).
     *
     * Gate: MeetingPolicy::viewPublic. Chỉ trả document is_public=true.
     *
     * @unauthenticated
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     *
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề tài liệu. Example: nghị quyết
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 50
     */
    public function publicListInMeeting(Meeting $meeting, FilterRequest $request)
    {
        Gate::authorize('viewPublic', $meeting);

        $items = $meeting->documents()
            ->with(['agenda', 'documentType', 'mediaFile'])
            ->where('is_public', true)
            ->when($request->input('search'), fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->paginate((int) ($request->limit ?? 50));

        return $this->successCollection(new MeetingDocumentCollection($items));
    }

    /**
     * Danh sách tài liệu họp công khai.
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề tài liệu. Example: nghị quyết
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam meeting_document_type_id integer Lọc theo loại tài liệu. Example: 1
     * @queryParam sort_by string Sắp xếp theo trường. Example: sort_order
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function public(FilterRequest $request)
    {
        $items = $this->meetingDocumentService->publicIndex($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new MeetingDocumentCollection($items));
    }

    /**
     * Chi tiết tài liệu họp công khai.
     *
     * @unauthenticated
     *
     * @urlParam meetingDocument integer required ID tài liệu họp. Example: 1
     */
    public function publicShow(MeetingDocument $meetingDocument)
    {
        return $this->successResource(new MeetingDocumentResource($this->meetingDocumentService->publicShow($meetingDocument)));
    }

    /**
     * Truy cập tài liệu công khai — track + 302 redirect tới file.
     *
     * Query `type` chọn loại counter:
     *  - `download` (mặc định): tăng `download_count`, log kind=document_download
     *  - `view`               : tăng `view_count`,    log kind=document_view
     *
     * @unauthenticated
     *
     * @urlParam meetingDocument integer required ID tài liệu họp. Example: 1
     *
     * @queryParam type string Loại truy cập (`download`|`view`). Mặc định `download`. Example: view
     */
    public function publicDownload(MeetingDocument $meetingDocument)
    {
        // Validate public visibility (mirror publicShow check) trước khi track.
        $this->meetingDocumentService->publicShow($meetingDocument);

        return $this->respondAccess($meetingDocument);
    }

    /**
     * Truy cập tài liệu (auth) — track + 302 redirect tới file.
     * FE dùng URL này thay cho `file_url` để BE đếm lượt tải/xem.
     *
     * Query `type` chọn loại counter:
     *  - `download` (mặc định): tăng `download_count`, log kind=document_download
     *  - `view`               : tăng `view_count`,    log kind=document_view
     *
     * @urlParam meetingDocument integer required ID tài liệu họp. Example: 1
     *
     * @queryParam type string Loại truy cập (`download`|`view`). Mặc định `download`. Example: view
     */
    public function download(MeetingDocument $meetingDocument)
    {
        return $this->respondAccess($meetingDocument);
    }

    private function respondAccess(MeetingDocument $meetingDocument)
    {
        $type = (string) request()->query('type', 'download');
        $info = $this->meetingDocumentService->trackAccess($meetingDocument, $type);

        return redirect()->away($info['url'], 302, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Danh sách tài liệu họp.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề tài liệu. Example: nghị quyết
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam meeting_document_type_id integer Lọc theo loại tài liệu. Example: 1
     * @queryParam sort_by string Sắp xếp theo trường. Example: sort_order
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang. Example: 10
     */
    public function index(FilterRequest $request)
    {
        $items = $this->meetingDocumentService->index($request->all(), (int) ($request->limit ?? 10));

        return $this->successCollection(new MeetingDocumentCollection($items));
    }

    /**
     * Chi tiết tài liệu họp.
     *
     * @urlParam meetingDocument integer required ID tài liệu họp. Example: 1
     */
    public function show(MeetingDocument $meetingDocument)
    {
        return $this->successResource(new MeetingDocumentResource($this->meetingDocumentService->show($meetingDocument)));
    }

    /**
     * Tạo tài liệu họp (mỗi tài liệu = 1 file).
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam title string required Tiêu đề tài liệu. Example: Dự thảo nghị quyết
     * @bodyParam meeting_document_type_id integer ID loại tài liệu họp. Example: 1
     * @bodyParam file file Tệp đính kèm (multipart/form-data, ≤10MB).
     * @bodyParam summary string Trích yếu tài liệu. Example: Tài liệu phục vụ thảo luận phiên sáng
     * @bodyParam is_public boolean required Công khai. Example: true
     */
    public function store(StoreMeetingDocumentRequest $request)
    {
        $item = $this->meetingDocumentService->store($request->validated(), $request->file('file'));

        return $this->successResource(new MeetingDocumentResource($item), 'Tạo tài liệu họp thành công!', 201);
    }

    /**
     * Cập nhật tài liệu họp (thay thế file qua `file` hoặc xóa qua `remove_file=true`).
     *
     * @urlParam meetingDocument integer required ID tài liệu họp. Example: 1
     * @bodyParam title string Tiêu đề tài liệu. Example: Dự thảo nghị quyết đã chỉnh sửa
     * @bodyParam meeting_document_type_id integer ID loại tài liệu họp. Example: 1
     * @bodyParam file file Tệp tài liệu mới (thay thế file cũ).
     * @bodyParam remove_file boolean Xóa file hiện tại (không upload mới). Example: false
     * @bodyParam summary string Trích yếu tài liệu. Example: Bản cập nhật sau góp ý
     */
    public function update(UpdateMeetingDocumentRequest $request, MeetingDocument $meetingDocument)
    {
        $item = $this->meetingDocumentService->update($meetingDocument, $request->validated(), $request->file('file'));

        return $this->successResource(new MeetingDocumentResource($item), 'Cập nhật tài liệu họp thành công!');
    }

    /**
     * Xóa tài liệu họp.
     *
     * @urlParam meetingDocument integer required ID tài liệu họp. Example: 1
     */
    public function destroy(MeetingDocument $meetingDocument)
    {
        $this->meetingDocumentService->destroy($meetingDocument);

        return $this->success(null, 'Xóa tài liệu họp thành công!');
    }

    /**
     * Xóa hàng loạt tài liệu họp.
     *
     * @bodyParam ids integer[] required Danh sách ID tài liệu cần xóa. Example: [1,2,3]
     */
    public function bulkDestroy(BulkDestroyCatalogRequest $request)
    {
        $this->meetingDocumentService->bulkDestroy($request->ids);

        return $this->success(null, 'Xóa hàng loạt thành công!');
    }

    /**
     * Sắp xếp lại thứ tự tài liệu họp.
     *
     * @bodyParam items object[] required Danh sách tài liệu cần sắp xếp. Example: [{"id":1,"sort_order":1},{"id":2,"sort_order":2}]
     */
    public function reorder(ReorderMeetingDocumentRequest $request)
    {
        $this->meetingDocumentService->reorder($request->validated('items'));

        return $this->success(null, 'Sắp xếp tài liệu họp thành công!');
    }

    /**
     * Public: xuất tổng hợp tài liệu cuộc họp.
     *
     * @unauthenticated
     *
     * Auth-optional. Guest (chưa login) chỉ xuất tài liệu is_public=true. Chair/op
     * /participant của meeting xuất đầy đủ (cả tài liệu private). Tự resolve auth qua
     * Bearer header / cookie accessToken (FE SPA pattern).
     *
     * Xuất ra các trường: STT, Tên tài liệu, Lượt xem, Người xem.
     * "Người xem" join tên user unique, guest gộp "Khách (xN)".
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function exportInMeeting(Meeting $meeting)
    {
        $isPrivileged = $this->meetingDocumentService->resolveIsPrivileged($meeting);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new MeetingDocumentExport($meeting->id, $isPrivileged),
            ExportFilename::make('tai-lieu-cuoc-hop'),
        );
    }

    /**
     * Public: xuất chi tiết lượt xem tài liệu — mỗi row = 1 lượt xem.
     *
     * @unauthenticated
     *
     * Auth-optional. Guest chỉ thấy view log của tài liệu is_public=true. Chair/op/
     * participant thấy đầy đủ. Bao gồm document_meta_view / document_view /
     * document_download. Guest viewer hiển thị "Khách".
     *
     * Xuất ra các trường: STT, Tên tài liệu, Người xem, Loại, Thời gian xem, IP.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     */
    public function exportViewsInMeeting(Meeting $meeting)
    {
        $isPrivileged = $this->meetingDocumentService->resolveIsPrivileged($meeting);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new MeetingDocumentViewExport($meeting->id, $isPrivileged),
            ExportFilename::make('luot-xem-tai-lieu'),
        );
    }
}
