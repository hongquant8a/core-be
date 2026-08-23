<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\LogActivity;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * GET /api/log-activities/me/export — user thường xuất nhật ký của CHÍNH MÌNH.
 */
class LogActivityMeExportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        $this->user = User::factory()->create();
        // Middleware set.permissions.team trả 403 nếu user không có role/permission nào
        // trong tổ chức. Gán đúng một quyền xem để user là thành viên hợp lệ nhưng
        // KHÔNG có log-activities.export — đúng tình huống cần kiểm chứng.
        $this->user->givePermissionTo('log-activities.index');
        Sanctum::actingAs($this->user);
        $this->withHeader('X-Organization-Id', (string) $this->org->id);
    }

    /** Không có quyền log-activities.export vẫn xuất được nhật ký của mình. */
    public function test_user_thuong_xuat_duoc_nhat_ky_cua_minh(): void
    {
        Excel::fake();

        $this->assertFalse($this->user->can('log-activities.export'));

        $this->get('/api/log-activities/me/export')->assertOk();

        // Tên file có timestamp (ExportFilename::make) nên phải so khớp bằng regex.
        Excel::matchByRegex();
        Excel::assertDownloaded('/^export__nhat-ky-hoat-dong_.*\\.xlsx$/');
    }

    /** Ép user_id = auth: client gửi user_id của người khác cũng không lấy được. */
    public function test_khong_lay_duoc_nhat_ky_cua_nguoi_khac(): void
    {
        $other = User::factory()->create();

        LogActivity::factory()->create(['user_id' => $this->user->id, 'organization_id' => $this->org->id, 'description' => 'CUA-TOI']);
        LogActivity::factory()->create(['user_id' => $other->id, 'organization_id' => $this->org->id, 'description' => 'CUA-NGUOI-KHAC']);

        Excel::fake();
        $this->get('/api/log-activities/me/export?user_id='.$other->id)->assertOk();

        Excel::matchByRegex();
        Excel::assertDownloaded('/^export__nhat-ky-hoat-dong_.*\\.xlsx$/', function ($export) {
            $descriptions = collect($export->collection())->pluck('description');

            return $descriptions->contains('CUA-TOI') && ! $descriptions->contains('CUA-NGUOI-KHAC');
        });
    }

    public function test_chua_dang_nhap_thi_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/log-activities/me/export', ['Authorization' => ''])->assertUnauthorized();
    }
}
