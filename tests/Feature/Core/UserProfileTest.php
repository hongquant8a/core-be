<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
        Sanctum::actingAs($this->admin);
    }

    public function test_profile_auto_created_when_user_created(): void
    {
        $u = User::factory()->create();

        $this->assertDatabaseHas('user_profiles', ['user_id' => $u->id]);
        $this->assertNotNull($u->fresh()->profile);
    }

    public function test_show_returns_existing_profile(): void
    {
        $u = User::factory()->create();
        $u->profile()->update(['phone' => '0901234567', 'gender' => 'male']);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson("/api/users/{$u->id}/profile");

        $res->assertOk();
        $res->assertJsonPath('data.user_id', $u->id);
        $res->assertJsonPath('data.phone', '0901234567');
        $res->assertJsonPath('data.gender', 'male');
    }

    public function test_show_creates_profile_if_missing(): void
    {
        $u = User::factory()->create();
        // Simulate legacy user without profile
        UserProfile::where('user_id', $u->id)->delete();
        $this->assertDatabaseMissing('user_profiles', ['user_id' => $u->id]);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson("/api/users/{$u->id}/profile");

        $res->assertOk();
        $this->assertDatabaseHas('user_profiles', ['user_id' => $u->id]);
    }

    public function test_update_persists_all_fields(): void
    {
        $u = User::factory()->create();

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->putJson("/api/users/{$u->id}/profile", [
                'phone' => '0907777777',
                'gender' => 'female',
                'birth_date' => '1995-03-20',
                'citizen_id' => '123456789012',
                'permanent_address' => '123 ABC',
                'temporary_address' => '456 DEF',
            ]);

        $res->assertOk();
        $profile = $u->fresh()->profile;
        $this->assertSame('0907777777', $profile->phone);
        $this->assertSame('female', $profile->gender);
        $this->assertSame('1995-03-20', $profile->birth_date->format('Y-m-d'));
        $this->assertSame('123456789012', $profile->citizen_id);
        $this->assertSame('123 ABC', $profile->permanent_address);
        $this->assertSame('456 DEF', $profile->temporary_address);
    }

    public function test_update_partial_does_not_clear_other_fields(): void
    {
        $u = User::factory()->create();
        $u->profile()->update([
            'phone' => '0901111111',
            'gender' => 'male',
            'permanent_address' => 'Original',
        ]);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->putJson("/api/users/{$u->id}/profile", [
                'phone' => '0902222222',
            ]);

        $res->assertOk();
        $profile = $u->fresh()->profile;
        $this->assertSame('0902222222', $profile->phone);
        $this->assertSame('male', $profile->gender);
        $this->assertSame('Original', $profile->permanent_address);
    }

    public function test_update_validates_birth_date_before_today(): void
    {
        $u = User::factory()->create();

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->putJson("/api/users/{$u->id}/profile", [
                'birth_date' => now()->addDay()->format('Y-m-d'),
            ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('birth_date');
    }

    public function test_update_validates_gender_enum(): void
    {
        $u = User::factory()->create();

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->putJson("/api/users/{$u->id}/profile", [
                'gender' => 'invalid',
            ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('gender');
    }

    public function test_update_rejects_duplicate_citizen_id(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u1->profile()->update(['citizen_id' => '999888777666']);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->putJson("/api/users/{$u2->id}/profile", [
                'citizen_id' => '999888777666',
            ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('citizen_id');
    }

    public function test_update_allows_same_citizen_id_for_self(): void
    {
        $u = User::factory()->create();
        $u->profile()->update(['citizen_id' => '111222333444']);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->putJson("/api/users/{$u->id}/profile", [
                'citizen_id' => '111222333444',
                'phone' => '0903333333',
            ]);

        $res->assertOk();
        $this->assertSame('111222333444', $u->fresh()->profile->citizen_id);
    }

    public function test_user_phone_accessor_reads_from_profile(): void
    {
        $u = User::factory()->create();
        $u->profile()->update(['phone' => '0904444444']);

        $this->assertSame('0904444444', $u->fresh()->phone);
    }

    public function test_put_users_endpoint_routes_profile_fields_into_user_profiles(): void
    {
        $u = User::factory()->create(['name' => 'Old Name']);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->putJson("/api/users/{$u->id}", [
                'name' => 'New Name',
                'gender' => 'male',
                'birth_date' => '1995-08-20',
                'permanent_address' => 'Combined update',
            ]);

        $res->assertOk();

        $fresh = $u->fresh();
        $this->assertSame('New Name', $fresh->name);
        $this->assertSame('male', $fresh->profile->gender);
        $this->assertSame('1995-08-20', $fresh->profile->birth_date->format('Y-m-d'));
        $this->assertSame('Combined update', $fresh->profile->permanent_address);
    }

    public function test_last_login_at_accessor_returns_max_token_created_at(): void
    {
        $u = User::factory()->create();
        $this->assertNull($u->last_login_at, 'no token yet → null');

        $u->createToken('first');
        usleep(1500); // separate timestamps
        $second = $u->createToken('second')->accessToken;

        $u->refresh()->load('tokens');
        $this->assertNotNull($u->last_login_at);
        $this->assertSame(
            $second->created_at->format('Y-m-d H:i:s'),
            $u->last_login_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_me_endpoint_includes_last_login_at(): void
    {
        // Sanctum::actingAs ở setUp không tạo token DB thật; issue token tay để có row.
        $this->admin->createToken('test-login');

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson('/api/user');

        $res->assertOk();
        $this->assertArrayHasKey('last_login_at', $res->json('data.user'));
        $this->assertNotNull($res->json('data.user.last_login_at'));
    }

    public function test_users_resource_does_not_expose_last_login_at(): void
    {
        $u = User::factory()->create();
        $u->createToken('test');

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson("/api/users/{$u->id}");

        $res->assertOk();
        $this->assertArrayNotHasKey('last_login_at', $res->json('data'));
    }

    public function test_put_users_combined_update_validates_profile_fields(): void
    {
        $u = User::factory()->create();

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->putJson("/api/users/{$u->id}", [
                'name' => 'X',
                'gender' => 'invalid',
            ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors('gender');
    }
}
