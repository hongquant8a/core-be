<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Requests\BulkDestroyCatalogRequest;
use App\Modules\Meeting\Requests\BulkUpdateStatusMeetingDocumentRequest;
use App\Modules\Meeting\Requests\ChangeStatusMeetingDocumentRequest;
use App\Modules\Meeting\Requests\ReorderMeetingDocumentRequest;
use App\Modules\Meeting\Requests\StoreMeetingDocumentRequest;
use App\Modules\Meeting\Requests\UpdateMeetingDocumentRequest;
use App\Modules\Meeting\Resources\MeetingDocumentCollection;
use App\Modules\Meeting\Resources\MeetingDocumentResource;
use App\Modules\Meeting\Services\MeetingDocumentService;

/**
 * @group Meeting - Tài liệu họp
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý tài liệu họp: danh sách, chi tiết, tạo, cập nhật, xóa, đổi trạng thái và sắp xếp.
 */
class MeetingDocumentController extends Controller
{
    public function __construct(private MeetingDocumentService $meetingDocumentService) {}

    /**
     * Danh sách tài liệu họp công khai.
     *
     * @unauthenticated
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề tài liệu. Example: nghị quyết
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam meeting_document_type_id integer Lọc theo loại tài liệu. Example: 1
     * @queryParam status string Lọc theo trạng thái tài liệu. Example: published
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
     * Danh sách tài liệu họp.
     *
     * @queryParam search string Từ khóa tìm kiếm theo tiêu đề tài liệu. Example: nghị quyết
     * @queryParam meeting_id integer Lọc theo cuộc họp. Example: 1
     * @queryParam meeting_document_type_id integer Lọc theo loại tài liệu. Example: 1
     * @queryParam status string Lọc theo trạng thái tài liệu. Example: draft
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
     * Tạo tài liệu họp (hỗ trợ nhiều file đính kèm trong 1 transaction).
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam title string required Tiêu đề tài liệu. Example: Dự thảo nghị quyết
     * @bodyParam meeting_document_type_id integer ID loại tài liệu họp. Example: 1
     * @bodyParam attachments file[] Mảng tệp đính kèm (multipart/form-data, key `attachments[]`). Mỗi tệp ≤10MB.
     * @bodyParam summary string Trích yếu tài liệu. Example: Tài liệu phục vụ thảo luận phiên sáng
     * @bodyParam is_public boolean required Công khai. Example: true
     * @bodyParam status string required Trạng thái tài liệu. Example: draft
     */
    public function store(StoreMeetingDocumentRequest $request)
    {
        $item = $this->meetingDocumentService->store(
            $request->validated(),
            (array) $request->file('attachments', [])
        );

        return $this->successResource(new MeetingDocumentResource($item), 'Tạo tài liệu họp thành công!', 201);
    }

    /**
     * Cập nhật tài liệu họp (thêm/xóa từng file qua attachments).
     *
     * @urlParam meetingDocument integer required ID tài liệu họp. Example: 1
     * @bodyParam title string Tiêu đề tài liệu. Example: Dự thảo nghị quyết đã chỉnh sửa
     * @bodyParam meeting_document_type_id integer ID loại tài liệu họp. Example: 1
     * @bodyParam attachments file[] Tệp đính kèm thêm vào (không thay thế attachments cũ). Example: (binary)
     * @bodyParam remove_attachment_ids integer[] ID `meeting_document_attachments` cần xóa. Example: [12,13]
     * @bodyParam summary string Trích yếu tài liệu. Example: Bản cập nhật sau góp ý
     * @bodyParam status string Trạng thái tài liệu. Example: published
     */
    public function update(UpdateMeetingDocumentRequest $request, MeetingDocument $meetingDocument)
    {
        $validated = $request->validated();
        $removeAttachmentIds = (array) ($validated['remove_attachment_ids'] ?? []);
        unset($validated['remove_attachment_ids']);

        $item = $this->meetingDocumentService->update(
            $meetingDocument,
            $validated,
            (array) $request->file('attachments', []),
            $removeAttachmentIds,
        );

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
     * Cập nhật trạng thái hàng loạt tài liệu họp.
     *
     * @bodyParam ids integer[] required Danh sách ID tài liệu cần cập nhật. Example: [1,2,3]
     * @bodyParam status string required Trạng thái mới của tài liệu. Example: archived
     */
    public function bulkUpdateStatus(BulkUpdateStatusMeetingDocumentRequest $request)
    {
        $this->meetingDocumentService->bulkUpdateStatus($request->ids, $request->status);

        return $this->success(null, 'Cập nhật trạng thái hàng loạt thành công!');
    }

    /**
     * Đổi trạng thái tài liệu họp.
     *
     * @urlParam meetingDocument integer required ID tài liệu họp. Example: 1
     * @bodyParam status string required Trạng thái mới của tài liệu. Example: published
     */
    public function changeStatus(ChangeStatusMeetingDocumentRequest $request, MeetingDocument $meetingDocument)
    {
        $item = $this->meetingDocumentService->changeStatus($meetingDocument, $request->status);

        return $this->successResource(new MeetingDocumentResource($item), 'Đổi trạng thái tài liệu họp thành công!');
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
}
