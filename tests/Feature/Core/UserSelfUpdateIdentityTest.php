<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Khoá 3 field định danh (name / email / user_name) khỏi PUT /api/users/me.
 *
 * Trước đây endpoint self-update nhận cả 'email' và 'user_name' — tức là chỉ cần chiếm được
 * Bearer token là đổi được thông tin đăng nhập, không cần mật khẩu hiện tại và cũng không có
 * bước xác minh email mới → chiếm luôn tài khoản lẫn kênh khôi phục. FE web và miniapp đã khoá
 * ở UI (readonly/disabled) nhưng đó chỉ là rào chắn mềm. Các test dưới khoá hành vi ở BE.
 */
class UserSelfUpdateIdentityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->org = Organization::firstOrCreate(['slug' => 'self-update-identity-test'], ['name' => 'Org', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        $this->user = User::factory()->create([
            'name' => 'Tên gốc',
            'email' => 'goc@example.com',
            'user_name' => 'tengoc',
        ]);
        // Super Admin: chứng minh việc khoá là do endpoint /me, không phải do thiếu quyền.
        $this->user->assignRole('Super Admin');
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function auth(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Organization-Id' => (string) $this->org->id,
        ];
    }

    public function test_update_me_cannot_change_identity_fields(): void
    {
        $this->putJson('/api/users/me', [
            'name' => 'Tên bị đổi',
            'email' => 'hacker@example.com',
            'user_name' => 'hacker',
            'phone' => '0901234567',
        ], $this->auth())->assertOk();

        $fresh = $this->user->fresh();
        $this->assertSame('Tên gốc', $fresh->name);
        $this->assertSame('goc@example.com', $fresh->email, 'PUT /users/me không được phép đổi email đăng nhập.');
        $this->assertSame('tengoc', $fresh->user_name);
        // Field self-edit hợp lệ vẫn phải lưu — chỉ 3 field định danh bị loại khỏi payload.
        $this->assertSame('0901234567', $fresh->phone);
    }

    public function test_admin_can_still_change_identity_fields_of_a_user(): void
    {
        $target = User::factory()->create(['email' => 'nguoikhac@example.com']);

        $this->putJson("/api/users/{$target->id}", [
            'name' => 'Tên admin đặt',
            'email' => 'moi@example.com',
            'user_name' => 'tenmoi',
        ], $this->auth())->assertOk();

        $fresh = $target->fresh();
        $this->assertSame('Tên admin đặt', $fresh->name);
        $this->assertSame('moi@example.com', $fresh->email);
        $this->assertSame('tenmoi', $fresh->user_name);
    }
}
