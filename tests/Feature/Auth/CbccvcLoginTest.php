<?php

namespace Tests\Feature\Auth;

use App\Modules\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CbccvcLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            ['key' => 'sso_cbccvc_enabled',  'value' => '1',                              'type' => 'boolean'],
            ['key' => 'sso_cbccvc_base_url', 'value' => 'https://cbccvc.example.test',    'type' => 'string'],
        ] as $row) {
            Setting::create($row + [
                'group' => 'sso_cbccvc',
                'is_public' => true,
                'label' => $row['key'],
                'sort_order' => 0,
            ]);
        }
    }

    public function test_returns_404_when_not_enabled(): void
    {
        Setting::where('key', 'sso_cbccvc_enabled')->update(['value' => '0']);
        Setting::clearCache();

        $res = $this->postJson('/api/auth/sso/cbccvc/login', [
            'username' => 'x', 'password' => 'y',
        ]);

        $res->assertStatus(404);
    }

    public function test_logs_in_and_returns_token_with_user(): void
    {
        Http::fake([
            'https://cbccvc.example.test/*' => Http::response([
                'data' => [
                    'user' => [
                        'id' => 153208,
                        'email' => 'giangpt@danang.gov.vn',
                        'fullname' => 'Phan Tấn Giang',
                        'username' => 'giangpt',
                    ],
                    'jwt' => 'ignored',
                ],
            ]),
        ]);

        $res = $this->postJson('/api/auth/sso/cbccvc/login', [
            'username' => 'giangpt',
            'password' => 'secret',
        ]);

        $res->assertOk();
        $res->assertJsonPath('data.user.email', 'giangpt@danang.gov.vn');
        $this->assertDatabaseHas('user_socials', [
            'provider' => 'cbccvc',
            'provider_user_id' => '153208',
        ]);
    }

    public function test_returns_401_when_credentials_invalid(): void
    {
        Http::fake([
            'https://cbccvc.example.test/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $res = $this->postJson('/api/auth/sso/cbccvc/login', [
            'username' => 'x', 'password' => 'wrong',
        ]);

        $res->assertStatus(401);
    }
}
