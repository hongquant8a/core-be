<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\LogActivity;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Middleware log.activity phải ghi header X-Device-Id vào nhật ký để phân biệt thiết bị.
 */
class LogActivityDeviceIdTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);
        $this->withHeader('X-Organization-Id', (string) $this->org->id);
    }

    public function test_ghi_device_id_tu_header(): void
    {
        $this->withHeader('X-Device-Id', 'browser-uuid-1')->getJson('/api/user')->assertOk();

        $this->assertSame('browser-uuid-1', LogActivity::latest('id')->first()->device_id);
    }

    public function test_khong_co_header_thi_de_null(): void
    {
        $this->getJson('/api/user')->assertOk();

        $this->assertNull(LogActivity::latest('id')->first()->device_id);
    }

    /** Header do client gửi nên phải cắt về đúng độ dài cột, tránh làm hỏng cả bản ghi. */
    public function test_cat_header_qua_dai_ve_100_ky_tu(): void
    {
        $this->withHeader('X-Device-Id', str_repeat('a', 500))->getJson('/api/user')->assertOk();

        $this->assertSame(100, mb_strlen(LogActivity::latest('id')->first()->device_id));
    }

    public function test_loc_danh_sach_theo_device_id(): void
    {
        LogActivity::factory()->create(['organization_id' => $this->org->id, 'device_id' => 'may-tinh-phong-ke-toan']);
        LogActivity::factory()->create(['organization_id' => $this->org->id, 'device_id' => 'dien-thoai-giam-doc']);

        $res = $this->getJson('/api/log-activities?device_id=phong-ke-toan')->assertOk();

        $deviceIds = collect($res->json('data'))->pluck('device_id')->all();
        $this->assertContains('may-tinh-phong-ke-toan', $deviceIds);
        $this->assertNotContains('dien-thoai-giam-doc', $deviceIds);
    }
}
