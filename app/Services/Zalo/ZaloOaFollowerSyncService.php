<?php

namespace App\Services\Zalo;

use App\Modules\Core\Models\ZaloOaFollower;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sync followers Zalo OA từ:
 *   GET https://openapi.zalo.me/v2.0/oa/getfollowers?data={"offset":0,"count":50}
 *   GET https://openapi.zalo.me/v2.0/oa/getprofile?data={"user_id":"..."}
 *
 * Strategy:
 *  - Paginate getfollowers tới hết.
 *  - Mỗi user_id -> getprofile -> upsert vào zalo_oa_followers.
 *  - Diff: user_id còn trong API -> giữ; không còn -> delete (đã unfollow).
 *
 * Docs:
 *  - https://developers.zalo.me/docs/official-account/quan-ly-nguoi-quan-tam/lay-danh-sach-nguoi-quan-tam
 *  - https://developers.zalo.me/docs/official-account/quan-ly-nguoi-quan-tam/lay-thong-tin-nguoi-quan-tam
 */
class ZaloOaFollowerSyncService
{
    private const GET_FOLLOWERS_URL = 'https://openapi.zalo.me/v2.0/oa/getfollowers';

    private const GET_PROFILE_URL = 'https://openapi.zalo.me/v2.0/oa/getprofile';

    /** Max followers / request — Zalo hỗ trợ tối đa 50. */
    private const PAGE_SIZE = 50;

    public function __construct(private ZaloOaTokenService $tokenService) {}

    /**
     * @return array{upserted: int, deleted: int, total_api: int}
     */
    public function sync(): array
    {
        $accessToken = $this->tokenService->getAccessToken();
        if (! $accessToken) {
            throw new RuntimeException('Chưa có Zalo access_token — chạy refresh trước.');
        }

        $apiUserIds = $this->fetchAllUserIds($accessToken);

        $upserted = 0;
        foreach ($apiUserIds as $userId) {
            if ($this->syncOneProfile($accessToken, $userId)) {
                $upserted++;
            }
        }

        // Diff delete: user trong DB nhưng không còn trong API response (đã unfollow).
        $deleted = ZaloOaFollower::query()
            ->when(! empty($apiUserIds), fn ($q) => $q->whereNotIn('user_id', $apiUserIds))
            ->when(empty($apiUserIds), fn ($q) => $q)
            ->delete();

        return [
            'upserted' => $upserted,
            'deleted' => $deleted,
            'total_api' => count($apiUserIds),
        ];
    }

    /**
     * Paginate getfollowers — Zalo trả {data: {followers: [{user_id}], total}, ...}.
     *
     * @return array<int, string> Danh sách user_id (string)
     */
    private function fetchAllUserIds(string $accessToken): array
    {
        $allIds = [];
        $offset = 0;

        while (true) {
            $response = Http::withHeaders(['access_token' => $accessToken])
                ->get(self::GET_FOLLOWERS_URL, [
                    'data' => json_encode(['offset' => $offset, 'count' => self::PAGE_SIZE]),
                ]);
            $data = $response->json() ?? [];

            $errorCode = (int) ($data['error'] ?? 0);
            if ($errorCode !== 0) {
                $msg = $data['message'] ?? 'unknown';
                throw new RuntimeException("Zalo getfollowers failed [{$errorCode}]: {$msg}");
            }

            $followers = $data['data']['followers'] ?? [];
            if (empty($followers)) {
                break;
            }

            foreach ($followers as $f) {
                if (! empty($f['user_id'])) {
                    $allIds[] = (string) $f['user_id'];
                }
            }

            $total = (int) ($data['data']['total'] ?? 0);
            $offset += count($followers);
            if ($offset >= $total || count($followers) < self::PAGE_SIZE) {
                break;
            }
        }

        return array_values(array_unique($allIds));
    }

    /**
     * Gọi getprofile cho 1 user_id + upsert vào DB. Trả về true nếu thành công, false nếu skip.
     */
    private function syncOneProfile(string $accessToken, string $userId): bool
    {
        try {
            $response = Http::withHeaders(['access_token' => $accessToken])
                ->get(self::GET_PROFILE_URL, [
                    'data' => json_encode(['user_id' => $userId]),
                ]);
            $data = $response->json() ?? [];

            $errorCode = (int) ($data['error'] ?? 0);
            if ($errorCode !== 0) {
                Log::warning("Zalo getprofile failed for user_id={$userId}", ['response' => $data]);

                return false;
            }

            $profile = $data['data'] ?? null;
            if (! $profile || empty($profile['user_id'])) {
                return false;
            }

            ZaloOaFollower::updateOrCreate(
                ['user_id' => (string) $profile['user_id']],
                [
                    'display_name' => $profile['display_name'] ?? null,
                    'user_alias' => $profile['user_id_by_app'] ?? null,
                    'avatar' => $profile['avatar'] ?? null,
                    'raw_profile' => $profile,
                    'last_synced_at' => now(),
                ]
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning("Zalo sync profile exception user_id={$userId}: ".$e->getMessage());

            return false;
        }
    }
}
