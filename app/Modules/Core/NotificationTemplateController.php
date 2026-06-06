<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\NotificationTemplate;
use App\Modules\Core\Requests\StoreNotificationTemplateRequest;
use App\Modules\Core\Requests\UpdateNotificationTemplateRequest;
use App\Modules\Core\Resources\NotificationTemplateResource;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Services\NotificationTemplateService;
use Illuminate\Http\Request;

/**
 * @group Core - Notification Templates
 *
 * Quản lý ZNS notification template per module.
 * Module được truyền qua query param `module`.
 */
class NotificationTemplateController extends Controller
{
    public function __construct(private NotificationTemplateService $templateService) {}

    /**
     * Lấy danh sách biến BE có sẵn cho module.
     *
     * FE dùng để hiển thị cho admin khi cấu hình variable_mapping cho ZNS template.
     *
     * @queryParam module required Module key (meeting | scheduling | task_assignment). Example: meeting
     */
    public function variables(Request $request)
    {
        $moduleKey = $this->validateModule($request);

        return $this->success($this->templateService->getVariables($moduleKey));
    }

    /**
     * Danh sách template config của một module.
     *
     * @queryParam module required Module key. Example: meeting
     */
    public function index(Request $request)
    {
        $moduleKey = $this->validateModule($request);

        $templates = NotificationTemplate::where('module_key', $moduleKey)
            ->where(function ($q) {
                $q->where('organization_id', $this->currentOrganizationId())
                    ->orWhereNull('organization_id');
            })
            ->orderBy('event_key')
            ->orderBy('channel')
            ->get();

        return $this->success(NotificationTemplateResource::collection($templates));
    }

    /**
     * Tạo mới template config.
     *
     * @bodyParam module_key string required Module key. Example: meeting
     * @bodyParam event_key string required Event key. Example: meeting_published
     * @bodyParam channel string required Kênh gửi (hiện tại chỉ zalo_zns). Example: zalo_zns
     * @bodyParam template_id string required ZNS template ID từ Zalo. Example: 263628
     * @bodyParam variable_mapping object Map biến BE → ZNS template variable name. Example: {"customer_name":"dai_bieu","meeting_title":"ky_hop"}
     * @bodyParam organization_id int nullable ID tổ chức (null = system default).
     * @bodyParam status string Trạng thái (active/inactive). Example: active
     */
    public function store(StoreNotificationTemplateRequest $request)
    {
        $template = NotificationTemplate::create($request->validated());

        return $this->successResource(new NotificationTemplateResource($template), 'Đã tạo template.');
    }

    /**
     * Cập nhật template config.
     *
     * @bodyParam template_id string ZNS template ID từ Zalo. Example: 263628
     * @bodyParam variable_mapping object Map biến BE → ZNS template variable name. Example: {"customer_name":"dai_bieu","meeting_title":"ky_hop"}
     * @bodyParam is_default boolean Template mặc định. Example: true
     * @bodyParam status string Trạng thái (active/inactive). Example: active
     */
    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $template)
    {
        $template->update($request->validated());

        return $this->successResource(new NotificationTemplateResource($template), 'Đã cập nhật template.');
    }

    /**
     * Xóa template config.
     */
    public function destroy(NotificationTemplate $template)
    {
        $template->delete();

        return $this->success(null, 'Đã xóa template.');
    }

    private function validateModule(Request $request): string
    {
        $moduleKey = $request->query('module');

        if (! $moduleKey || ! in_array($moduleKey, NotificationModuleEnum::values(), true)) {
            abort(400, 'Thiếu hoặc sai module. Module hợp lệ: '.implode(', ', NotificationModuleEnum::values()));
        }

        return $moduleKey;
    }

    private function currentOrganizationId(): int
    {
        $teamId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        return $teamId ? (int) $teamId : 0;
    }
}
