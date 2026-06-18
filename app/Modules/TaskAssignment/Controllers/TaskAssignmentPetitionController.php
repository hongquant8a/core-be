<?php

namespace App\Modules\TaskAssignment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskAssignment\Models\TaskAssignmentPetition;
use App\Modules\TaskAssignment\Requests\BulkDestroyPetitionRequest;
use App\Modules\TaskAssignment\Requests\ChangeStatusPetitionRequest;
use App\Modules\TaskAssignment\Requests\StorePetitionRequest;
use App\Modules\TaskAssignment\Requests\UpdatePetitionRequest;
use App\Modules\TaskAssignment\Requests\UpdateProgressPetitionRequest;
use App\Modules\TaskAssignment\Resources\PetitionCollection;
use App\Modules\TaskAssignment\Resources\PetitionResource;
use App\Modules\TaskAssignment\Services\TaskAssignmentPetitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group TaskAssignment - Đơn thư
 *
 * Quản lý đơn thư của các phòng ban.
 */
class TaskAssignmentPetitionController extends Controller
{
    public function __construct(private TaskAssignmentPetitionService $service) {}

    /**
     * Danh sách phòng ban available cho user hiện tại (dropdown)
     *
     * Trả về danh sách phòng ban mà user đang đăng nhập có quyền xem đơn thư.
     * Nếu 1 phòng: trả về mảng 1 phần tử. Nếu 2+ phòng: trả về đầy đủ.
     *
     * @response 200 {"success": true, "data": [{"id": 1, "name": "Phòng A"}]}
     */
    public function availableDepartments(): JsonResponse
    {
        return $this->success($this->service->getAvailableDepartments());
    }

    /**
     * Thống kê đơn thư
     */
    public function stats(Request $request): JsonResponse
    {
        return $this->success($this->service->stats($request->all()));
    }

    /**
     * Danh sách đơn thư
     *
     * @queryParam search string Tìm kiếm theo tên, CCCD, SĐT, email, nội dung. Example: Nguyễn
     * @queryParam processing_status string Trạng thái xử lý.
     * @queryParam department_id int ID phòng ban.
     * @queryParam submission_date_from date Ngày gửi đơn từ.
     * @queryParam submission_date_to date Ngày gửi đơn đến.
     * @queryParam deadline_date_from date Hạn xử lý từ.
     * @queryParam deadline_date_to date Hạn xử lý đến.
     * @queryParam sort_by string Sắp xếp theo cột. Example: submission_date
     * @queryParam sort_order string Thứ tự sắp xếp (asc/desc). Example: desc
     * @queryParam limit int Số bản ghi/trang. Example: 20 
     */
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);

        return $this->successCollection(new PetitionCollection($this->service->index($request->all(), $limit)));
    }

    /**
     * Chi tiết đơn thư
     *
     * @urlParam petition int required ID đơn thư. Example: 1
     */
    public function show(TaskAssignmentPetition $petition): JsonResponse
    {
        return $this->successResource(new PetitionResource($this->service->show($petition)));
    }

    /**
     * Tạo đơn thư mới
     *
     * @bodyParam department_id int required ID phòng ban tiếp nhận. Example: 1
     * @bodyParam submission_date date required Ngày gửi đơn. Example: 2026-06-10
     * @bodyParam deadline_date date Hạn xử lý. Example: 2026-06-15
     * @bodyParam sender_name string required Người gửi đơn. Example: Nguyễn Văn A
     * @bodyParam sender_address string Địa chỉ.
     * @bodyParam sender_cccd string CCCD.
     * @bodyParam sender_phone string SĐT.
     * @bodyParam sender_email string Email.
     * @bodyParam content string Nội dung đơn.
     * @bodyParam attachments file[] Đính kèm.
     */
    public function store(StorePetitionRequest $request): JsonResponse
    {
        $petition = $this->service->store(
            $request->validated(),
            $request->file('attachments', [])
        );

        return $this->successResource(new PetitionResource($petition), 'Tạo đơn thư thành công!');
    }

    /**
     * Cập nhật đơn thư
     *
     * @urlParam petition int required ID đơn thư. Example: 1
     */
    public function update(UpdatePetitionRequest $request, TaskAssignmentPetition $petition): JsonResponse
    {
        $petition = $this->service->update(
            $petition,
            $request->validated(),
            $request->file('attachments', []),
            $request->input('remove_attachment_ids', [])
        );

        return $this->successResource(new PetitionResource($petition), 'Cập nhật đơn thư thành công!');
    }

    /**
     * Xóa đơn thư
     *
     * @urlParam petition int required ID đơn thư. Example: 1
     */
    public function destroy(TaskAssignmentPetition $petition): JsonResponse
    {
        $this->service->destroy($petition);

        return $this->success(null, 'Đã xóa đơn thư!');
    }

    /**
     * Xóa hàng loạt đơn thư
     *
     * @bodyParam ids array required Danh sách ID. Example: [1, 2]
     */
    public function bulkDestroy(BulkDestroyPetitionRequest $request): JsonResponse
    {
        $ids = $request->input('ids');
        $this->service->bulkDestroy($ids);

        return $this->success(null, "Đã xóa thành công " . count($ids) . " đơn thư!");
    }

    /**
     * Đổi trạng thái đơn thư
     *
     * @urlParam petition int required ID đơn thư. Example: 1
     * @bodyParam processing_status string required Trạng thái mới. Example: processing
     */
    public function changeStatus(ChangeStatusPetitionRequest $request, TaskAssignmentPetition $petition): JsonResponse
    {
        $petition = $this->service->changeStatus($petition, $request->input('processing_status'));

        return $this->successResource(new PetitionResource($petition), 'Cập nhật trạng thái thành công!');
    }

    /**
     * Mở khóa đơn thư (Quản lý)
     *
     * @urlParam petition int required ID đơn thư. Example: 1
     */
    public function unlock(TaskAssignmentPetition $petition): JsonResponse
    {
        $petition = $this->service->unlock($petition);

        return $this->successResource(new PetitionResource($petition), 'Đã mở khóa đơn thư thành công!');
    }

    /**
     * Cập nhật tiến độ xử lý đơn thư
     *
     * @urlParam petition int required ID đơn thư. Example: 1
     * @bodyParam completed_at datetime Ngày hoàn thành xử lý.
     * @bodyParam document_number string Số ký hiệu văn bản trả lời.
     * @bodyParam document_excerpt string Trích yếu văn bản.
     * @bodyParam response_content string Tóm tắt nội dung trả lời.
     * @bodyParam attachments file[] File đính kèm trả lời.
     * @bodyParam remove_attachment_ids int[] DS ID attachment cần xóa.
     */
    public function updateProgress(UpdateProgressPetitionRequest $request, TaskAssignmentPetition $petition): JsonResponse
    {
        $petition = $this->service->updateProgress(
            $petition,
            $request->validated(),
            $request->file('attachments', []),
            $request->input('remove_attachment_ids', [])
        );

        return $this->successResource(new PetitionResource($petition), 'Cập nhật tiến độ thành công!');
    }

    /**
     * Xuất danh sách đơn thư
     *
     * Xuất ra các trường: STT, người gửi đơn, CCCD, số điện thoại, email, địa chỉ,
     * nội dung đơn, ngày gửi đơn, hạn xử lý, phòng ban, trạng thái, ngày hoàn thành,
     * số ký hiệu VB, trích yếu VB, nội dung trả lời, người tạo, người cập nhật,
     * ngày tạo, ngày cập nhật, ID.
     *
     * @queryParam search string Tìm kiếm.
     * @queryParam processing_status string Trạng thái xử lý.
     * @queryParam department_id int ID phòng ban.
     * @queryParam submission_date_from date Ngày gửi đơn từ.
     * @queryParam submission_date_to date Ngày gửi đơn đến.
     */
    public function export(Request $request)
    {
        return $this->service->export($request->all());
    }
}
