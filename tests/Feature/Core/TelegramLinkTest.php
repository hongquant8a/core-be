<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Jobs\ProcessTelegramUpdateJob;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserProfile;
use App\Modules\Core\Services\TelegramLinkService;
use App\Services\Notification\DTOs\SendResult;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Liên kết Telegram bằng deep link: webhook, token, và các nhánh hỏng.
 *
 * Trọng tâm là những chỗ sai thì hại thật: webhook giả mạo liên kết được tài khoản người khác,
 * token hết hạn vẫn dùng được, và một chat_id gắn cho hai người cùng lúc.
 */
class TelegramLinkTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private const SECRET = 'secret-webhook-token';

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingSeeder::class);
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->org = Organization::firstOrCreate(['slug' => 'telegram-link-test'], ['name' => 'Org', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        Setting::where('key', 'tg_webhook_secret')->update(['value' => self::SECRET]);
        Setting::where('key', 'tg_bot_username')->update(['value' => 'danatec_test_bot']);
        Setting::where('key', 'tg_bot_token')->update(['value' => '123:ABC']);
        Setting::where('key', 'tg_enabled')->update(['value' => '1']);
        Setting::clearCache();
    }

    /** Mọi tin nhắn gửi ra Telegram đều giả lập — test không được gọi ra ngoài. */
    private function fakeSender(): void
    {
        $notifier = Mockery::mock(NotificationService::class);
        $notifier->shouldReceive('send')->andReturn([new SendResult(channel: 'telegram', success: true, messageId: '1')]);
        $this->app->instance(NotificationService::class, $notifier);
    }

    /** Middleware tenant đòi user có vai trò trong tổ chức đang gửi ở header. */
    private function authHeaders(User $user): array
    {
        $user->assignRole('Super Admin');

        return [
            'Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken,
            'X-Organization-Id' => (string) $this->org->id,
        ];
    }

    public function test_webhook_tu_choi_khi_khong_co_secret_hop_le(): void
    {
        Queue::fake();

        $this->postJson('/api/telegram/webhook', ['message' => ['chat' => ['id' => 999], 'text' => '/start abc']])
            ->assertStatus(403);

        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'sai-secret'])
            ->postJson('/api/telegram/webhook', ['message' => ['chat' => ['id' => 999], 'text' => '/start abc']])
            ->assertStatus(403);

        Queue::assertNothingPushed();
    }

    public function test_webhook_dung_secret_tra_200_va_day_vao_hang_doi(): void
    {
        Queue::fake();

        $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => self::SECRET])
            ->postJson('/api/telegram/webhook', ['message' => ['chat' => ['id' => 999], 'text' => '/start abc']])
            ->assertOk();

        Queue::assertPushedOn('notifications', ProcessTelegramUpdateJob::class);
    }

    public function test_token_hop_le_thi_luu_chat_id_va_xoa_token(): void
    {
        $this->fakeSender();

        $user = User::factory()->create();
        $profile = UserProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->update(['telegram_link_token' => 'token-hop-le', 'telegram_token_expires_at' => now()->addHours(2)]);

        app(TelegramLinkService::class)->handleStart('555000', 'token-hop-le');

        $profile->refresh();
        $this->assertSame('555000', $profile->telegram_chat_id);
        $this->assertNull($profile->telegram_link_token);
        $this->assertNotNull($profile->telegram_linked_at);
    }

    public function test_token_het_han_khong_lien_ket_duoc(): void
    {
        $this->fakeSender();

        $user = User::factory()->create();
        $profile = UserProfile::firstOrCreate(['user_id' => $user->id]);
        $profile->update(['telegram_link_token' => 'token-cu', 'telegram_token_expires_at' => now()->subMinute()]);

        app(TelegramLinkService::class)->handleStart('555001', 'token-cu');

        $this->assertNull($profile->refresh()->telegram_chat_id);
    }

    public function test_chat_id_trung_duoc_go_khoi_user_cu(): void
    {
        $this->fakeSender();

        $nguoiCu = User::factory()->create();
        $profileCu = UserProfile::firstOrCreate(['user_id' => $nguoiCu->id]);
        $profileCu->update(['telegram_chat_id' => '777', 'telegram_linked_at' => now()]);

        $nguoiMoi = User::factory()->create();
        $profileMoi = UserProfile::firstOrCreate(['user_id' => $nguoiMoi->id]);
        $profileMoi->update(['telegram_link_token' => 'token-moi', 'telegram_token_expires_at' => now()->addHour()]);

        app(TelegramLinkService::class)->handleStart('777', 'token-moi');

        $this->assertNull($profileCu->refresh()->telegram_chat_id);
        $this->assertSame('777', $profileMoi->refresh()->telegram_chat_id);
    }

    public function test_tao_link_moi_vo_hieu_hoa_token_cu(): void
    {
        $user = User::factory()->create();

        $service = app(TelegramLinkService::class);
        $service->createLink($user);
        $tokenDau = UserProfile::where('user_id', $user->id)->value('telegram_link_token');

        $ketQua = $service->createLink($user);
        $tokenSau = UserProfile::where('user_id', $user->id)->value('telegram_link_token');

        $this->assertNotSame($tokenDau, $tokenSau);
        $this->assertStringContainsString("https://t.me/danatec_test_bot?start={$tokenSau}", $ketQua['deep_link']);
        $this->assertLessThanOrEqual(64, strlen($tokenSau));
    }

    public function test_user_bi_vo_hieu_hoa_thi_mat_lien_ket(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        UserProfile::firstOrCreate(['user_id' => $user->id])->update([
            'telegram_chat_id' => '888',
            'telegram_linked_at' => now(),
        ]);

        $user->update(['status' => 'inactive']);

        $this->assertNull(UserProfile::where('user_id', $user->id)->value('telegram_chat_id'));
    }

    public function test_self_update_khong_dat_duoc_chat_id(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/users/me', ['telegram_chat_id' => '123456789'])
            ->assertOk();

        $this->assertNull(UserProfile::where('user_id', $user->id)->value('telegram_chat_id'));
    }

    public function test_huy_lien_ket_xoa_sach_du_lieu(): void
    {
        $user = User::factory()->create();
        UserProfile::firstOrCreate(['user_id' => $user->id])->update([
            'telegram_chat_id' => '999',
            'telegram_linked_at' => now(),
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson('/api/users/me/telegram')
            ->assertOk();

        $this->assertNull(UserProfile::where('user_id', $user->id)->value('telegram_chat_id'));
    }
}
