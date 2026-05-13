<?php

namespace App\Console\Commands;

use App\Services\Zalo\ZaloOaFollowerSyncService;
use App\Services\Zalo\ZaloOaTokenService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Schedule 45 phút (xem routes/console.php):
 *  1. Refresh Zalo OA access_token + refresh_token (TTL access 1h, schedule 45p để buffer 15p).
 *  2. Sync followers từ /v2.0/oa/getfollowers + foreach /v2.0/oa/getprofile.
 *  3. Upsert vào zalo_oa_followers, delete user_id không còn trong response.
 */
class ZaloRefreshAndSyncCommand extends Command
{
    protected $signature = 'zalo:refresh-and-sync
                            {--skip-refresh : Bỏ refresh token, chỉ sync followers}
                            {--skip-sync : Chỉ refresh token, bỏ sync}';

    protected $description = 'Refresh Zalo OA token + sync followers vào DB (snapshot full + diff delete)';

    public function handle(ZaloOaTokenService $tokenService, ZaloOaFollowerSyncService $syncService): int
    {
        if (! $this->option('skip-refresh')) {
            $this->info('[1/2] Refresh Zalo OA token...');
            try {
                $tokenService->refresh();
                $this->info('  OK — token mới đã lưu vào settings.');
            } catch (Throwable $e) {
                $this->error('  FAILED: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        if (! $this->option('skip-sync')) {
            $this->info('[2/2] Sync followers...');
            try {
                $result = $syncService->sync();
                $this->info(sprintf(
                    '  OK — upserted=%d, deleted=%d, total_api=%d',
                    $result['upserted'],
                    $result['deleted'],
                    $result['total_api'],
                ));
            } catch (Throwable $e) {
                $this->error('  FAILED: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
