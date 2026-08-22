<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Luồng đổi mật khẩu cá nhân: PUT /api/users/me/password.
 *
 * Trước đây đổi mật khẩu đi chung PUT /api/users/me và không kiểm tra mật khẩu cũ →
 * chỉ cần chiếm được Bearer token là chiếm luôn tài khoản. Các test dưới khoá hành vi đó lại.
 */
class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->org = Organization::firstOrCreate(['slug' => 'change-password-test'], ['name' => 'Org', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        $this->user = User::factory()->create(['password' => Hash::make('oldpassword')]);
        $this->user->assignRole('Super Admin');
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /** SetPermissionsTeamId bắt buộc header X-Organization-Id cho mọi request đã auth. */
    private function auth(?string $token = null): array
    {
        return [
            'Authorization' => 'Bearer '.($token ?? $this->token),
            'X-Organization-Id' => (string) $this->org->id,
        ];
    }

    public function test_change_password_requires_current_password(): void
    {
        $this->putJson('/api/users/me/password', [
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ], $this->auth())
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('oldpassword', $this->user->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $this->putJson('/api/users/me/password', [
            'current_password' => 'sai-mat-khau',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ], $this->auth())
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('oldpassword', $this->user->fresh()->password));
    }

    public function test_change_password_rejects_same_password(): void
    {
        $this->putJson('/api/users/me/password', [
            'current_password' => 'oldpassword',
            'password' => 'oldpassword',
            'password_confirmation' => 'oldpassword',
        ], $this->auth())
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_change_password_rejects_mismatched_confirmation(): void
    {
        $this->putJson('/api/users/me/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword',
            'password_confirmation' => 'khacnhau',
        ], $this->auth())
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_change_password_succeeds_with_correct_current_password(): void
    {
        $this->putJson('/api/users/me/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ], $this->auth())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue(Hash::check('newpassword', $this->user->fresh()->password));
    }

    public function test_change_password_revokes_other_sessions_but_keeps_current(): void
    {
        $otherToken = $this->user->createToken('other-device')->plainTextToken;
        $this->assertCount(2, $this->user->tokens);

        $this->putJson('/api/users/me/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ], $this->auth())->assertOk();

        // Guard được cache giữa các request trong cùng test → phải quên đi, nếu không
        // request sau vẫn "đăng nhập" bằng user đã resolve từ request trước.
        $this->app['auth']->forgetGuards();

        // Token của thiết bị khác chết ngay — token đang dùng vẫn sống (không đá user ra).
        $this->getJson('/api/users/me', $this->auth($otherToken))->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/users/me', $this->auth())->assertOk();
        $this->assertCount(1, $this->user->fresh()->tokens);
    }

    public function test_update_me_cannot_change_password(): void
    {
        $this->putJson('/api/users/me', [
            'name' => 'Tên mới',
            'password' => 'hacked123',
            'password_confirmation' => 'hacked123',
        ], $this->auth())->assertOk();

        $fresh = $this->user->fresh();
        $this->assertSame('Tên mới', $fresh->name);
        $this->assertTrue(Hash::check('oldpassword', $fresh->password), 'PUT /users/me không được phép đổi mật khẩu.');
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->putJson('/api/users/me/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertStatus(401);
    }
}
