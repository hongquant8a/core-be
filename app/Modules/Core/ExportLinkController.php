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
 *  2. BE trả về 1 URL còn hạn 5 phút, trỏ tới GET /api/exports/{type}/{filename}
 *     (middleware 'signed', không cần token) — verify bằng chữ ký, rồi
 *     forward nguyên request sang đúng action export() đã đăng ký trong
 *     config/exports.php (không có logic export riêng ở đây).
 *
 * {filename} nằm trên PATH (không phải query) vì zmp-sdk downloadFile({url})
 * đặt tên file tải về theo segment cuối của URL path, KHÔNG đọc header
 * Content-Disposition — nếu {filename} cố định/không có, các export khác
 * scope nhưng cùng {type} (vd assigned/received cùng dùng
 * task-assignment-items) sẽ luôn ra cùng 1 tên file, dễ gây nhầm lẫn dù nội
 * dung khác nhau (đã bắt gặp thật). FE truyền filename mong muốn qua query
 * `filename` khi gọi link().
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

        $filename = $this->sanitizeFilename($request->query('filename') ?: $type);

        // Nhúng user_id + organization_id (team context của Spatie Permission,
        // set bởi middleware set.permissions.team từ header X-Organization-Id)
        // vào params đã ký — route đích (không auth:sanctum, không có header)
        // dùng để khôi phục lại đúng ngữ cảnh đó (xem download()). Thiếu
        // organization_id khiến các model/scope lọc theo team trả sai/thiếu/
        // trộn dữ liệu nhiều tổ chức — đã bắt gặp thật khi thiếu bước này.
        // An toàn vì chữ ký bảo đảm các giá trị không bị sửa: đổi mà không ký
        // lại thì route đích trả 403 trước khi tới action export().
        //
        // Tên "_ctx_*" (thay vì "user_id"/"organization_id" trần) CỐ Ý đặt tiền
        // tố riêng biệt: đây là param nội bộ, không phải filter nghiệp vụ. Nếu
        // đặt tên trần, khi forward sang action export() thật, nó lọt vào
        // $request->all() và bị scopeFilter() hiểu nhầm thành filter thật —
        // đã bắt được thật với "user_id" (TaskAssignmentItem::scopeFilter() có
        // sẵn filter "user_id" nghĩa là "chỉ hiện việc mà user này được gán",
        // hoàn toàn khác mục đích của mình là "khôi phục ai đang gọi API") —
        // khiến export luôn trả về 0 dòng. Tiền tố "_ctx_" đảm bảo không bao
        // giờ trùng tên với filter nghiệp vụ nào, kể cả filter thêm sau này.
        $url = URL::temporarySignedRoute(
            'exports.signed',
            now()->addMinutes(5),
            array_merge($request->except('filename'), [
                'type' => $type,
                'filename' => $filename,
                '_ctx_user_id' => auth()->id(),
                '_ctx_org_id' => getPermissionsTeamId(),
            ]),
        );

        return $this->success(['url' => $url]);
    }

    public function download(Request $request, string $type, string $filename)
    {
        $entry = $this->resolve($type);

        // Một số export (vd đơn thư) lọc theo phòng ban của auth()->user() bên
        // trong service — route này không qua auth:sanctum nên phải khôi phục
        // lại đúng user đó cho riêng request này. setUser() chỉ set trong bộ
        // nhớ của request hiện tại (không tạo session/cookie/token mới) — guard
        // mặc định là Sanctum RequestGuard, không có onceUsingId() (chỉ
        // SessionGuard mới có), nên phải tự load User rồi setUser() thủ công.
        //
        // QUAN TRỌNG: User::$guard_name = 'web' (Spatie Permission) — mọi role/
        // permission check của Spatie resolve qua guard 'web', không phải guard
        // mặc định ('api'). Middleware SetPermissionsTeamId gốc set CẢ 2 guard
        // (xem Auth::guard('web')->setUser($user) ở đó); thiếu bước set guard
        // 'web' khiến Spatie coi như "guest" → mọi scope/permission dựa trên
        // role âm thầm resolve rỗng, export trả về 0 dòng dù data có thật (đã
        // tái hiện + xác nhận bug này qua so sánh trực tiếp route gốc: 64 rows
        // vs route này: 1 row, cùng data/org/user).
        if ($request->filled('_ctx_user_id')) {
            $user = User::find($request->query('_ctx_user_id'));
            if ($user) {
                Auth::setUser($user);
                Auth::guard('web')->setUser($user);
            }
        }

        if ($request->filled('_ctx_org_id')) {
            setPermissionsTeamId((int) $request->query('_ctx_org_id'));
        }

        // Bỏ 2 param nội bộ khỏi query trước khi forward — dù đã đặt tiền tố
        // "_ctx_" để tránh trùng tên filter nghiệp vụ hiện tại, vẫn xoá luôn
        // cho sạch, không để lọt vào $request->all() của action export() thật.
        // "expires"/"signature" là do Laravel tự thêm khi ký URL — cũng dọn
        // luôn cho chắc. Route param {type}/{filename} nằm trên path, không
        // qua đây (đã verify: không xuất hiện trong $request->all()).
        $request->query->remove('_ctx_user_id');
        $request->query->remove('_ctx_org_id');
        $request->query->remove('expires');
        $request->query->remove('signature');

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

    // Chỉ giữ ký tự an toàn cho 1 URL path segment (chữ, số, - _ .) — filename
    // này chỉ dùng để đặt tên file tải về native, không ảnh hưởng tới
    // Content-Disposition thật mà export() trả về.
    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9\-_.]+/', '_', $filename);

        return $filename !== '' && str_ends_with(strtolower($filename), '.xlsx')
            ? $filename
            : $filename.'.xlsx';
    }
}
