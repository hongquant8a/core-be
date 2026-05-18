<?php

namespace App\Modules\Meeting;

use App\Http\Controllers\Controller;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use App\Modules\Meeting\Models\MeetingDiscussionRegistrationAttachment;
use App\Modules\Meeting\Requests\ReorderMeetingDiscussionRegistrationAttachmentRequest;
use App\Modules\Meeting\Requests\StoreMeetingDiscussionRegistrationAttachmentRequest;
use App\Modules\Meeting\Resources\MeetingDiscussionRegistrationAttachmentResource;
use App\Modules\Meeting\Services\MeetingDiscussionRegistrationAttachmentService;

/**
 * @group Meeting - Tệp đính kèm thảo luận/chất vấn
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Multi-attachment cho đăng ký thảo luận / chất vấn. Pattern giống tệp đính kèm
 * ghi chú cá nhân — nested 2 cấp: meeting → registration → attachments.
 */
class MeetingDiscussionRegistrationAttachmentController extends Controller
{
    public function __construct(private MeetingDiscussionRegistrationAttachmentService $service) {}

    /**
     * In-registration: thêm file đính kèm vào đăng ký thảo luận/chất vấn.
     *
     * Gate `update,meetingDiscussionRegistration` (owner đăng ký HOẶC chair/op meeting).
     * meeting_discussion_registration_id auto-inject từ URL.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     *
     * @bodyParam file file required Tệp đính kèm (≤10MB). Example: (binary)
     * @bodyParam file_name string Tên hiển thị do user đặt. Mặc định = tên file gốc. Example: Slide trình chiếu
     * @bodyParam sort_order integer Thứ tự. Example: 1
     */
    public function storeInRegistration(
        Meeting $meeting,
        MeetingDiscussionRegistration $meetingDiscussionRegistration,
        StoreMeetingDiscussionRegistrationAttachmentRequest $request,
    ) {
        $item = $this->service->store($request->validated(), $request->file('file'));
        $this->broadcastRegistrationUpdated($meetingDiscussionRegistration);

        return $this->successResource(
            new MeetingDiscussionRegistrationAttachmentResource($item),
            'Thêm tệp đính kèm thành công!',
            201,
        );
    }

    /**
     * In-registration: xóa file đính kèm.
     *
     * Gate `delete,meetingDiscussionRegistrationAttachment` (owner đăng ký HOẶC chair/op).
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     * @urlParam meetingDiscussionRegistrationAttachment integer required ID file. Example: 1
     */
    public function destroyInRegistration(
        Meeting $meeting,
        MeetingDiscussionRegistration $meetingDiscussionRegistration,
        MeetingDiscussionRegistrationAttachment $meetingDiscussionRegistrationAttachment,
    ) {
        $this->service->destroy($meetingDiscussionRegistrationAttachment);
        $this->broadcastRegistrationUpdated($meetingDiscussionRegistration);

        return $this->success(null, 'Xóa tệp đính kèm thành công!');
    }

    /**
     * In-registration: sắp xếp lại thứ tự file đính kèm.
     *
     * Gate `update,meetingDiscussionRegistration`.
     *
     * @urlParam meeting integer required ID cuộc họp. Example: 1
     * @urlParam meetingDiscussionRegistration integer required ID đăng ký. Example: 1
     *
     * @bodyParam items object[] required Danh sách. Example: [{"id":1,"sort_order":1},{"id":2,"sort_order":2}]
     */
    public function reorderInRegistration(
        Meeting $meeting,
        MeetingDiscussionRegistration $meetingDiscussionRegistration,
        ReorderMeetingDiscussionRegistrationAttachmentRequest $request,
    ) {
        $this->service->reorder($request->validated('items'));
        $this->broadcastRegistrationUpdated($meetingDiscussionRegistration);

        return $this->success(null, 'Sắp xếp tệp đính kèm thành công!');
    }

    /**
     * Sau mỗi action trên attachment, re-emit `discussion-registration.updated` với
     * full payload registration (kèm attachments[]) để FE Tab 7/Chair/Operator sync
     * realtime. Tránh stale chip "0 tệp" khi user A upload xong, user B chưa fetch lại.
     */
    private function broadcastRegistrationUpdated(MeetingDiscussionRegistration $registration): void
    {
        $registration->refresh()->load(['participant', 'agenda', 'mediaFile', 'attachments.mediaFile']);
        broadcast(new \App\Modules\Meeting\Events\MeetingDiscussionRegistrationUpdated($registration))->toOthers();
    }
}
