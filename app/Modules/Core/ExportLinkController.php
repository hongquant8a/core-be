<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * @group Core - Export link
 *
 * Cầu nối chung cho mọi export Excel cần tải qua Zalo Mini App: zmp-sdk
 * downloadFile()/openWebview() chỉ nhận 1 URL, không đính kèm được header
 * Authorization — nên URL đưa cho chúng phải tự xác thực bằng chữ ký
 * (Laravel signed route) thay vì Bearer token.
 *
 * Flow 2 bước:
 *  1. FE gọi GET /api/exports/{type}/link (auth:sanctum như bình thường) —
 *     đây là nơi phân quyền thực sự diễn ra (check permission theo config).
 *  2. BE trả về 1 URL còn hạn 5 phút, trỏ tới GET /api/exports/{type}
 *     (middleware 'signed', không cần token) — verify bằng chữ ký, rồi
 *     forward nguyên request sang đúng action export() đã đăng ký trong
 *     config/exports.php (không có logic export riêng ở đây).
 *
 * Thêm export type mới: chỉ cần thêm 1 phần tử vào config/exports.php,
 * KHÔNG cần route/controller mới.
 */
class ExportLinkController extends Controller
{
    public function link(Request $request, string $type)
    {
        $entry = $this->resolve($type);

        if (! auth()->user()->can($entry['permission'])) {
            return $this->forbidden();
        }

        // Nhúng user_id vào params đã ký — route đích (không auth:sanctum) dùng
        // để khôi phục đúng user cho request đó (xem download()). An toàn vì
        // chữ ký bảo đảm user_id không bị sửa: đổi user_id mà không ký lại thì
        // route đích trả 403 trước khi tới action export().
        $url = URL::temporarySignedRoute(
            'exports.signed',
            now()->addMinutes(5),
            array_merge($request->all(), ['type' => $type, 'user_id' => auth()->id()]),
        );

        return $this->success(['url' => $url]);
    }

    public function download(Request $request, string $type)
    {
        $entry = $this->resolve($type);

        // Một số export (vd đơn thư) lọc theo phòng ban của auth()->user() bên
        // trong service — route này không qua auth:sanctum nên phải khôi phục
        // lại đúng user đó cho riêng request này. setUser() chỉ set trong bộ
        // nhớ của request hiện tại (không tạo session/cookie/token mới) — guard
        // mặc định là Sanctum RequestGuard, không có onceUsingId() (chỉ
        // SessionGuard mới có), nên phải tự load User rồi setUser() thủ công.
        if ($request->filled('user_id')) {
            $user = User::find($request->query('user_id'));
            if ($user) {
                Auth::setUser($user);
            }
        }

        // `[$class, $method]` với $class là string bị PHP hiểu nhầm thành gọi
        // tĩnh ("cannot be called statically") — dùng cú pháp "Class@method" để
        // Container tự resolve instance (qua constructor DI) rồi mới gọi method,
        // đồng thời vẫn tự inject + validate đúng FormRequest (FilterRequest,...)
        // của action đích như khi gọi qua route bình thường.
        return app()->call($entry['controller'].'@'.$entry['action']);
    }

    private function resolve(string $type): array
    {
        $entry = config("exports.{$type}");

        abort_unless($entry, 404, "Export type không tồn tại: {$type}");

        return $entry;
    }
}
