<?php

namespace App\Modules\Core\Middleware;

use App\Modules\Core\Models\LogActivity as LogActivityModel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stevebauman\Location\Facades\Location;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware ghi nhật ký truy cập của người dùng vào bảng log_activities.
 *
 * Khi thêm resource hoặc action mới vào API: cập nhật resourceLabel() (resource => nhãn),
 * actionLabels trong descriptionFromRouteName(), pathActions trong descriptionFromPath(),
 * và route parameters trong descriptionFromRouteName() (params) để mô tả chính xác.
 */
class LogActivity
{
    /** Các trường nhạy cảm không lưu vào request_data. */
    protected static array $excludedRequestKeys = [
        'password', 'password_confirmation', '_token', 'token',
        'email_smtp_password', 'sms_password', 'zalo_password', 'chat_api_key',
        'api_gemini_token', 'api_deepseek_token', 'api_chatgpt_token',
        'firebase_service_account', 'api_google_maps_token',
    ];

    /** Đường dẫn không ghi log (vd: health check, polling endpoints). */
    protected static array $excludedPaths = ['/up', '/api/notifications/me/unread-count'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldLog($request)) {
            return $response;
        }

        $this->log($request, $response->getStatusCode());

        return $response;
    }

    protected function shouldLog(Request $request): bool
    {
        foreach (self::$excludedPaths as $path) {
            if (str_starts_with($request->path(), ltrim($path, '/'))) {
                return false;
            }
        }

        return true;
    }

    protected function log(Request $request, int $statusCode): void
    {
        try {
            $user = Auth::guard('sanctum')->user();
            $userType = $user ? class_basename($user) : 'Guest';
            $userId = $user?->id;
            $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

            if (! $organizationId) {
                $headerOrg = $request->header('X-Organization-Id') ?? $request->header('x-organization-id');
                if (is_numeric($headerOrg)) {
                    $organizationId = (int) $headerOrg;
                }
            }

            LogActivityModel::create([
                'description' => $this->buildDescription($request),
                'user_type' => $userType,
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'route' => $request->fullUrl(),
                'method_type' => $request->method(),
                'status_code' => $statusCode,
                'ip_address' => $request->ip() ?? '0.0.0.0',
                'country' => $this->resolveCountry($request),
                'user_agent' => $request->userAgent(),
                'request_data' => $this->sanitizeRequestData($request),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function buildDescription(Request $request): string
    {
        $routeName = $request->route()?->getName();
        // Skip Laravel auto-generated route names (`generated::xxxx`) — đây là tên random
        // Laravel mutate vào route object khi cần serialize cho URL generation / route caching.
        // Dùng path-based description thay vì leak chuỗi random vào nhật ký.
        if ($routeName && ! str_starts_with($routeName, 'generated::')) {
            return $this->descriptionFromRouteName($routeName, $request);
        }

        return $this->descriptionFromPath($request);
    }

    /** Mô tả từ path khi không có route name (vd: GET api/posts → Truy cập danh sách bài viết). */
    protected function descriptionFromPath(Request $request): string
    {
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);
        $method = $request->method();

        if (($segments[0] ?? '') === 'api') {
            array_shift($segments);
        }

        $resource = $segments[0] ?? '';
        $sub = $segments[1] ?? null;

        // Auth: api/auth/login, api/auth/forgot-password...
        if ($resource === 'auth') {
            $authLabels = ['login' => 'Đăng nhập', 'logout' => 'Đăng xuất', 'forgot-password' => 'Quên mật khẩu', 'reset-password' => 'Đặt lại mật khẩu'];

            return $authLabels[$sub] ?? "Xác thực: {$sub}";
        }

        // Settings: api/settings/public
        if ($resource === 'settings' && $sub === 'public') {
            return 'Xem cấu hình công khai';
        }

        // Action trong path: export, import, stats, bulk-delete, delete-by-date, clear...
        $pathActions = [
            'export' => 'Xuất dữ liệu',
            'import' => 'Nhập dữ liệu',
            'stats' => 'Xem thống kê',
            'public' => 'Xem dữ liệu công khai',
            'public-options' => 'Xem dữ liệu dropdown công khai',
            'bulk-delete' => 'Xóa hàng loạt',
            'bulk-status' => 'Cập nhật trạng thái hàng loạt',
            'tree' => 'Xem cây',
            'delete-by-date' => 'Xóa theo khoảng thời gian',
            'clear' => 'Xóa toàn bộ',
            'checkin' => 'Điểm danh',
            'checkin-by-token' => 'Điểm danh QR',
            'manual-checkin' => 'Điểm danh hộ',
            'mark-absent' => 'Báo vắng',
            'respond' => 'Phản hồi mời họp',
            'complete' => 'Đánh dấu hoàn thành',
            'approve' => 'Duyệt',
            'reject' => 'Từ chối',
            'lock-attendance' => 'Khoá điểm danh',
            'unlock-attendance' => 'Mở khoá điểm danh',
            'end-early' => 'Kết thúc sớm',
            'highlight-agenda' => 'Chiếu chương trình',
            'highlight-discussion' => 'Chiếu phát biểu',
            'qr-token' => 'Xem QR điểm danh',
            'attendees' => 'Quản lý đại biểu nhóm',
            'duplicate' => 'Sao chép',
            'reorder' => 'Sắp xếp lại',
            'reopen' => 'Mở lại',
            'reject' => 'Từ chối duyệt',
            'week-matrix' => 'Xem ma trận tuần',
            'sync-members' => 'Đồng bộ thành viên nhóm',
            'driver-view' => 'Xem lịch của lái xe',
        ];
        if ($sub && isset($pathActions[$sub])) {
            return $pathActions[$sub].' '.$this->resourceLabel(str_replace('-', '_', $resource));
        }

        $resourceLabel = $this->resourceLabel(str_replace('-', '_', $resource));
        $id = $sub && is_numeric($sub) ? $sub : null;

        $labels = [
            'GET' => $id ? 'Xem chi tiết' : 'Truy cập danh sách',
            'POST' => 'Tạo mới',
            'PUT' => 'Cập nhật',
            'PATCH' => 'Cập nhật',
            'DELETE' => 'Xóa',
        ];
        $actionLabel = $labels[$method] ?? $method;
        $suffix = $id ? " #{$id}" : '';

        return trim("{$actionLabel} {$resourceLabel}{$suffix}") ?: "{$method} /{$path}";
    }

    protected function descriptionFromRouteName(string $routeName, Request $request): string
    {
        $parts = explode('.', $routeName);
        $resource = $parts[0] ?? '';
        $action = $parts[1] ?? 'access';

        $actionLabels = [
            'index' => 'Truy cập danh sách',
            'show' => 'Xem chi tiết',
            'store' => 'Tạo mới',
            'update' => 'Cập nhật',
            'destroy' => 'Xóa',
            'stats' => 'Xem thống kê',
            'tree' => 'Xem cây',
            'export' => 'Xuất dữ liệu',
            'import' => 'Nhập dữ liệu',
            'changeStatus' => 'Đổi trạng thái',
            'bulkDestroy' => 'Xóa hàng loạt',
            'bulkUpdateStatus' => 'Cập nhật trạng thái hàng loạt',
            'incrementView' => 'Tăng lượt xem',
            'destroyByDate' => 'Xóa theo khoảng thời gian',
            'destroyAll' => 'Xóa toàn bộ',
            'public' => 'Xem dữ liệu công khai',
            'updateProgress' => 'Cập nhật tiến độ',
            'approve' => 'Duyệt',
            'reject' => 'Từ chối',
            'duplicate' => 'Sao chép',
            'reorder' => 'Sắp xếp lại',
        ];

        $actionLabel = $actionLabels[$action] ?? $action;
        $resourceLabel = $this->resourceLabel($resource);

        $params = $request->route()?->parameters() ?? [];
        $id = $params['user']
            ?? $params['post']
            ?? $params['organization']
            ?? $params['category']
            ?? $params['role']
            ?? $params['logActivity']
            ?? $params['document']
            ?? $params['documentType']
            ?? $params['issuingAgency']
            ?? $params['issuingLevel']
            ?? $params['documentSigner']
            ?? $params['documentField']
            ?? $params['taskAssignmentDepartment']
            ?? $params['taskAssignmentType']
            ?? $params['taskAssignmentItemType']
            ?? $params['taskAssignmentDocument']
            ?? $params['taskAssignmentItem']
            ?? $params['taskAssignmentItemReport']
            ?? $params['schedule']
            ?? $params['schedulingEmployee']
            ?? $params['schedulingEmployeeGroup']
            ?? $params['id']
            ?? null;
        $suffix = $id ? ' #'.(is_object($id) ? $id->getKey() : $id) : '';

        return trim("{$actionLabel} {$resourceLabel}{$suffix}");
    }

    protected function resourceLabel(string $resource): string
    {
        $resource = str_replace('_', '-', $resource);
        $labels = [
            'users' => 'người dùng',
            'posts' => 'bài viết',
            'post-categories' => 'danh mục bài viết',
            'permissions' => 'quyền',
            'roles' => 'vai trò',
            'organizations' => 'tổ chức',
            'auth' => 'xác thực',
            'log-activities' => 'nhật ký truy cập',
            'documents' => 'văn bản',
            'document-types' => 'loại văn bản',
            'issuing-agencies' => 'cơ quan ban hành',
            'issuing-levels' => 'cấp ban hành',
            'document-signers' => 'người ký',
            'document-fields' => 'lĩnh vực',
            'settings' => 'cấu hình hệ thống',
            'task-assignment-departments' => 'Phòng ban giao việc',
            'task-assignment-employees' => 'Nhân viên giao việc',
            'task-assignment-types' => 'Loại văn bản giao việc',
            'task-assignment-item-types' => 'Loại công việc',
            'task-assignment-documents' => 'Văn bản giao việc',
            'task-assignment-items' => 'Công việc',
            'task-assignment-item-reports' => 'Báo cáo công việc',
            'schedules' => 'lịch công tác',
            'schedules-executive' => 'lịch công tác - Thường trực',
            'schedules-office'    => 'lịch công tác - Lãnh đạo',
            'scheduling-employees' => 'nhân sự lịch công tác',
            'scheduling-employee-groups' => 'nhóm nhân sự',
            'scheduling-settings' => 'cấu hình lịch công tác',
            'scheduling-filter-presets' => 'bộ lọc cá nhân',
        ];

        return $labels[$resource] ?? str_replace('-', ' ', $resource);
    }

    protected function resolveCountry(Request $request): ?string
    {
        $ip = $request->ip();
        if (! $ip || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return null;
        }

        try {
            $position = Location::get($ip);

            return $position ? $position->countryName : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function sanitizeRequestData(Request $request): ?array
    {
        try {
            $data = array_merge($request->query(), $request->except(self::$excludedRequestKeys));
        } catch (\Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException $e) {
            // Temp file đã bị PHP dọn trước khi middleware chạy (stale upload).
            // Chỉ lấy query data, bỏ qua phần body/files.
            $data = $request->query();
        }

        if (empty($data)) {
            return null;
        }

        // Giới hạn kích thước để tránh lưu quá nhiều dữ liệu
        $encoded = json_encode($data);
        if (strlen($encoded) > 65535) {
            return ['_truncated' => true, 'size' => strlen($encoded)];
        }

        return $data;
    }
}
