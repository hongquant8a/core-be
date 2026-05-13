<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\ZaloOaFollower;
use App\Modules\Core\Resources\ZaloOaFollowerResource;
use Illuminate\Http\Request;

/**
 * @group Zalo OA - Followers
 *
 * Danh sách follower của Zalo Official Account, sync từ Zalo API mỗi 45 phút
 * (xem command `zalo:refresh-and-sync`). Endpoint này để FE/admin pick user_id
 * khi gán `users.zalo_user_id` (cho phép gửi tin nhắn qua kênh Zalo).
 */
class ZaloOaFollowerController extends Controller
{
    /**
     * Danh sách follower (phân trang).
     *
     * @queryParam search string Tìm theo tên hiển thị. Example: Nguyễn
     * @queryParam sort_by string Sắp xếp theo trường (display_name, last_synced_at). Example: display_name
     * @queryParam sort_order string asc | desc. Example: asc
     * @queryParam limit integer Số bản ghi mỗi trang (1-100). Example: 20
     */
    public function index(Request $request)
    {
        $limit = max(1, min(100, (int) $request->input('limit', 20)));
        $search = $request->input('search');
        $sortBy = in_array($request->input('sort_by'), ['display_name', 'last_synced_at', 'id'], true)
            ? $request->input('sort_by')
            : 'display_name';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';

        $items = ZaloOaFollower::query()
            ->when($search, fn ($q, $s) => $q->where('display_name', 'like', '%'.$s.'%'))
            ->orderBy($sortBy, $sortOrder)
            ->paginate($limit);

        return $this->success([
            'items' => ZaloOaFollowerResource::collection($items->getCollection()),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }
}
