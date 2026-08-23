<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\FcmToken;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * DELETE /api/fcm-tokens/me — người dùng tự tắt thông báo trên thiết bị đang dùng.
 */
class FcmTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($org->id);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('log-activities.index');
        Sanctum::actingAs($this->user);
        $this->withHeader('X-Organization-Id', (string) $org->id);
    }

    public function test_chi_xoa_thiet_bi_dang_goi(): void
    {
        FcmToken::create(['user_id' => $this->user->id, 'device_id' => 'may-nay', 'fcm_token' => 'token-a']);
        FcmToken::create(['user_id' => $this->user->id, 'device_id' => 'may-khac', 'fcm_token' => 'token-b']);

        $this->withHeader('X-Device-Id', 'may-nay')
            ->deleteJson('/api/fcm-tokens/me')
            ->assertOk()
            ->assertJsonPath('data.deleted', 1);

        $this->assertSame(0, FcmToken::where('device_id', 'may-nay')->count());
        $this->assertSame(1, FcmToken::where('device_id', 'may-khac')->count(), 'thiết bị khác phải giữ nguyên');
    }

    public function test_khong_dung_toi_thiet_bi_cua_nguoi_khac(): void
    {
        $other = User::factory()->create();
        FcmToken::create(['user_id' => $other->id, 'device_id' => 'may-nay', 'fcm_token' => 'token-cua-nguoi-khac']);

        $this->withHeader('X-Device-Id', 'may-nay')
            ->deleteJson('/api/fcm-tokens/me')
            ->assertOk()
            ->assertJsonPath('data.deleted', 0);

        $this->assertSame(1, FcmToken::where('user_id', $other->id)->count());
    }

    /** Thiếu header thì phải từ chối, không được xoá theo mỗi user_id. */
    public function test_thieu_header_thi_422_va_khong_xoa_gi(): void
    {
        FcmToken::create(['user_id' => $this->user->id, 'device_id' => 'may-nay', 'fcm_token' => 'token-a']);

        $this->deleteJson('/api/fcm-tokens/me')->assertStatus(422);

        $this->assertSame(1, FcmToken::where('user_id', $this->user->id)->count());
    }

    public function test_chua_dang_nhap_thi_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->deleteJson('/api/fcm-tokens/me', [], ['Authorization' => ''])->assertUnauthorized();
    }

    /**
     * Tắt rồi bật lại ngay: dấu vết throttle của middleware phải bị xoá, nếu không
     * request kế tiếp tưởng "không có gì đổi" và thiết bị không được đăng ký lại.
     */
    public function test_bat_lai_duoc_ngay_sau_khi_tat(): void
    {
        $headers = ['X-FCM-Token' => 'token-a', 'X-Device-Id' => 'may-nay'];

        $this->withHeaders($headers)->getJson('/api/user')->assertOk();
        $this->assertSame(1, FcmToken::where('user_id', $this->user->id)->count());

        $this->withHeader('X-Device-Id', 'may-nay')->deleteJson('/api/fcm-tokens/me')->assertOk();
        $this->assertSame(0, FcmToken::where('user_id', $this->user->id)->count());

        $this->withHeaders($headers)->getJson('/api/user')->assertOk();
        $this->assertSame(1, FcmToken::where('user_id', $this->user->id)->count());
    }
}
