<?php

namespace App\Modules\Meeting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Support\ExportFilename;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingGuest;
use App\Modules\Meeting\Models\MeetingInvitationTemplate;
use App\Modules\Meeting\Requests\StoreMeetingInvitationTemplateRequest;
use App\Modules\Meeting\Requests\UpdateMeetingInvitationTemplateRequest;
use App\Modules\Meeting\Resources\MeetingInvitationTemplateResource;
use App\Modules\Meeting\Services\MeetingInvitationGenerator;
use App\Modules\Meeting\Services\MeetingInvitationTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * @group Meeting - Template giấy mời
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc với endpoint yêu cầu auth). Example: 1
 *
 * Quản lý template .docx dùng cho xuất giấy mời họp.
 */
class MeetingInvitationTemplateController extends Controller
{
    public function __construct(
        private MeetingInvitationTemplateService $service,
        private MeetingInvitationGenerator $generator,
    ) {}

    /**
     * Cheatsheet biến template giấy mời.
     *
     * @response 200 {"success": true, "data": {"scalar": {...}}}
     */
    public function variables()
    {
        return $this->success([
            'scalar' => MeetingInvitationGenerator::SCALAR_VARIABLES,
        ]);
    }

    /**
     * Tải file template mẫu (.docx).
     */
    public function downloadSample()
    {
        $path = $this->generator->generateSample();

        return response()->download(
            $path,
            'meeting-invitation-template-sample.docx'
        )->deleteFileAfterSend(true);
    }

    /**
     * Danh sách template giấy mời.
     */
    public function index(FilterRequest $request)
    {
        $items = $this->service->index($request->all(), (int) ($request->limit ?? 10));

        return $this->success([
            'items' => MeetingInvitationTemplateResource::collection($items->getCollection()),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Chi tiết template giấy mời.
     */
    public function show(MeetingInvitationTemplate $meetingInvitationTemplate)
    {
        return $this->successResource(new MeetingInvitationTemplateResource(
            $this->service->show($meetingInvitationTemplate)
        ));
    }

    /**
     * Danh sách template giấy mời dùng trong cuộc họp (cho dialog chọn mẫu).
     */
    public function listForExport(FilterRequest $request)
    {
        $items = $this->service->index($request->all(), (int) ($request->limit ?? 100));

        return $this->success([
            'items' => MeetingInvitationTemplateResource::collection($items->getCollection()),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Tạo template giấy mời mới.
     */
    public function store(StoreMeetingInvitationTemplateRequest $request)
    {
        $item = $this->service->store($request->validated(), $request->file('file'));

        return $this->successResource(new MeetingInvitationTemplateResource($item), 'Tạo template thành công!', 201);
    }

    /**
     * Cập nhật template giấy mời.
     */
    public function update(UpdateMeetingInvitationTemplateRequest $request, MeetingInvitationTemplate $meetingInvitationTemplate)
    {
        $item = $this->service->update(
            $meetingInvitationTemplate,
            $request->validated(),
            $request->file('file'),
        );

        return $this->successResource(new MeetingInvitationTemplateResource($item), 'Cập nhật template thành công!');
    }

    /**
     * Xóa template giấy mời.
     */
    public function destroy(MeetingInvitationTemplate $meetingInvitationTemplate)
    {
        $this->service->destroy($meetingInvitationTemplate);

        return $this->success(null, 'Xóa template thành công!');
    }

    /**
     * Xuất giấy mời cho khách mời trong meeting (đơn lẻ hoặc hàng loạt).
     *
     * @bodyParam meeting_id integer required ID cuộc họp. Example: 1
     * @bodyParam template_id integer required ID template giấy mời. Example: 1
     * @bodyParam guest_id integer ID khách mời (nếu xuất đơn lẻ). Example: 1
     */
    public function exportInvitation(Request $request)
    {
        $request->validate([
            'meeting_id' => 'required|integer|exists:meetings,id',
            'template_id' => 'required|integer|exists:meeting_invitation_templates,id',
            'guest_id' => 'nullable|integer|exists:meeting_guests,id',
        ]);

        $meeting = Meeting::findOrFail((int) $request->input('meeting_id'));

        Gate::authorize('exportReports', $meeting);

        $template = MeetingInvitationTemplate::findOrFail((int) $request->input('template_id'));
        $guestId = $request->input('guest_id');

        if ($guestId) {
            $guest = MeetingGuest::where('meeting_id', $meeting->id)->findOrFail((int) $guestId);
            $path = $this->generator->generateSingle($meeting, $guest, $template);
            
            $guestSlug = Str::slug((string) $guest->name) ?: 'khach';
            $meetingSlug = Str::slug((string) $meeting->title) ?: ('meeting-'.$meeting->id);
            $filename = ExportFilename::make('giay-moi-'.$guestSlug.'-'.$meetingSlug, 'docx');
            
            return response()->download($path, $filename)->deleteFileAfterSend(true);
        } else {
            $path = $this->generator->generateBatch($meeting, $template);
            
            $meetingSlug = Str::slug((string) $meeting->title) ?: ('meeting-'.$meeting->id);
            $filename = ExportFilename::make('giay-moi-tap-the-'.$meetingSlug, 'docx');
            
            return response()->download($path, $filename)->deleteFileAfterSend(true);
        }
    }
}
