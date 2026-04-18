<?php

namespace Tests\Feature\Auth;

use App\Modules\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            ['key' => 'sso_danang_enabled',       'value' => '1',                          'type' => 'boolean'],
            ['key' => 'sso_danang_base_url',      'value' => 'https://sso.example.test',   'type' => 'string'],
            ['key' => 'sso_danang_client_id',    'value' => 'test-client',                'type' => 'string'],
            ['key' => 'sso_danang_client_secret', 'value' => 'test-secret',                'type' => 'string'],
            ['key' => 'sso_danang_redirect_uri', 'value' => 'https://app.test/cb',        'type' => 'string'],
        ] as $row) {
            Setting::create($row + [
                'group' => 'sso_danang',
                'is_public' => true,
                'label' => $row['key'],
                'sort_order' => 0,
            ]);
        }
    }

    public function test_returns_404_when_provider_not_enabled(): void
    {
        Setting::where('key', 'sso_danang_enabled')->update(['value' => '0']);
        Setting::clearCache();

        $res = $this->postJson('/api/auth/sso/exchange', [
            'provider' => 'sso_danang',
            'code' => 'x',
        ]);

        $res->assertStatus(404);
    }

    public function test_returns_422_for_unknown_provider(): void
    {
        $res = $this->postJson('/api/auth/sso/exchange', [
            'provider' => 'unknown_provider',
            'code' => 'x',
        ]);

        $res->assertStatus(422);
    }

    public function test_exchanges_code_and_returns_token_with_user(): void
    {
        Http::fake([
            'https://sso.example.test/oauth2/token' => Http::response([
                'access_token' => 'atok',
                'expires_in' => 3600,
            ]),
            'https://sso.example.test/oauth2/userinfo' => Http::response([
                'email' => 'giangpt@danang.gov.vn',
                'name' => 'Phan Tấn Giang',
                'sub' => 'giangpt',
                'upn' => 'giangpt',
            ]),
        ]);

        $res = $this->postJson('/api/auth/sso/exchange', [
            'provider' => 'sso_danang',
            'code' => 'auth-code-xyz',
        ]);

        $res->assertOk();
        $res->assertJsonPath('success', true);
        $res->assertJsonStructure([
            'data' => ['access_token', 'token_type', 'user' => ['id', 'name', 'email']],
        ]);
        $this->assertDatabaseHas('users', ['email' => 'giangpt@danang.gov.vn']);
        $this->assertDatabaseHas('user_socials', [
            'provider' => 'sso_danang',
            'provider_user_id' => 'giangpt',
        ]);
    }

    public function test_returns_400_when_code_invalid(): void
    {
        Http::fake([
            'https://sso.example.test/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $res = $this->postJson('/api/auth/sso/exchange', [
            'provider' => 'sso_danang',
            'code' => 'bad',
        ]);

        $res->assertStatus(400);
    }

    public function test_returns_502_when_upstream_unreachable(): void
    {
        Http::fake([
            'https://sso.example.test/oauth2/token' => Http::response('Service Unavailable', 503),
        ]);

        $res = $this->postJson('/api/auth/sso/exchange', [
            'provider' => 'sso_danang',
            'code' => 'x',
        ]);

        $res->assertStatus(502);
    }
}
