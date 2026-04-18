<?php

namespace Tests\Unit\Auth;

use App\Modules\Auth\Services\UserSyncService;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserSocial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserSyncService::class);
    }

    public function test_creates_new_user_when_email_and_social_both_missing(): void
    {
        $userinfo = [
            'email' => 'giangpt@danang.gov.vn',
            'name' => 'Phan Tấn Giang',
            'sub' => ' giangpt ',
            'raw' => ['upn' => 'giangpt'],
        ];

        $user = $this->service->syncFromUserinfo('sso_danang', $userinfo);

        $this->assertNotNull($user->id);
        $this->assertSame('giangpt@danang.gov.vn', $user->email);
        $this->assertSame('Phan Tấn Giang', $user->name);
        $this->assertNull($user->user_name);
        $this->assertSame('active', $user->status);
        $this->assertDatabaseHas('user_socials', [
            'user_id' => $user->id,
            'provider' => 'sso_danang',
            'provider_user_id' => 'giangpt',
        ]);
    }

    public function test_links_social_to_existing_user_when_email_matches(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@danang.gov.vn',
            'name' => 'Existing Local User',
        ]);
        $originalPasswordHash = $existingUser->password;

        $userinfo = [
            'email' => 'existing@danang.gov.vn',
            'name' => 'SSO Returned Name',
            'sub' => 'xyz123',
            'raw' => [],
        ];

        $user = $this->service->syncFromUserinfo('sso_danang', $userinfo);

        $this->assertSame($existingUser->id, $user->id);
        $this->assertSame('Existing Local User', $user->fresh()->name, 'name không được ghi đè');
        $this->assertSame($originalPasswordHash, $user->fresh()->password, 'password không được ghi đè');
        $this->assertDatabaseHas('user_socials', [
            'user_id' => $existingUser->id,
            'provider' => 'sso_danang',
            'provider_user_id' => 'xyz123',
        ]);
    }

    public function test_returns_linked_user_and_refreshes_provider_data_on_subsequent_login(): void
    {
        $user = User::factory()->create(['email' => 'user@danang.gov.vn']);
        UserSocial::create([
            'user_id' => $user->id,
            'provider' => 'sso_danang',
            'provider_user_id' => 'abc',
            'provider_data' => ['old' => 'data'],
            'linked_at' => now(),
        ]);

        $userinfo = [
            'email' => 'user@danang.gov.vn',
            'name' => 'User',
            'sub' => 'abc',
            'raw' => ['new' => 'data'],
        ];

        $resolved = $this->service->syncFromUserinfo('sso_danang', $userinfo);

        $this->assertSame($user->id, $resolved->id);
        $this->assertSame(1, UserSocial::where('user_id', $user->id)->count(), 'không tạo social trùng');
        $this->assertSame(['new' => 'data'], UserSocial::where('user_id', $user->id)->first()->provider_data);
    }

    public function test_assigns_default_role_to_newly_created_user_when_setting_configured(): void
    {
        $org = Organization::firstOrCreate(['slug' => 'test-org'], ['name' => 'Test Org', 'status' => 'active']);
        setPermissionsTeamId($org->id);

        $role = Role::create(['name' => 'Member', 'guard_name' => 'web', 'organization_id' => $org->id]);
        Setting::create([
            'key' => 'auth_auto_create_default_role_id',
            'value' => (string) $role->id,
            'group' => 'auth',
            'is_public' => false,
            'type' => 'integer',
            'label' => 'Role mặc định',
            'sort_order' => 1,
        ]);

        $userinfo = [
            'email' => 'new@danang.gov.vn',
            'name' => 'New',
            'sub' => 'new1',
            'raw' => [],
        ];

        $user = $this->service->syncFromUserinfo('sso_danang', $userinfo);

        $this->assertTrue($user->hasRole('Member'));
    }
}
