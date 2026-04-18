<?php

namespace Tests\Unit\Auth;

use App\Modules\Auth\Services\Providers\SsoDanangProvider;
use App\Modules\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoDanangProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSsoDanangSettings();
    }

    private function seedSsoDanangSettings(): void
    {
        foreach ([
            ['key' => 'sso_danang_base_url',       'value' => 'https://sso.example.test', 'type' => 'string'],
            ['key' => 'sso_danang_client_id',      'value' => 'test-client',              'type' => 'string'],
            ['key' => 'sso_danang_client_secret',  'value' => 'test-secret',              'type' => 'string'],
            ['key' => 'sso_danang_redirect_uri',   'value' => 'https://app.test/cb',      'type' => 'string'],
        ] as $row) {
            Setting::create($row + [
                'group' => 'sso_danang',
                'is_public' => false,
                'label' => $row['key'],
                'sort_order' => 0,
            ]);
        }
    }

    public function test_exchanges_code_for_userinfo(): void
    {
        Http::fake([
            'https://sso.example.test/oauth2/token' => Http::response([
                'access_token' => 'atok',
                'id_token' => 'itok',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
            'https://sso.example.test/oauth2/userinfo' => Http::response([
                'email' => 'giangpt@danang.gov.vn',
                'name' => 'Phan Tấn Giang',
                'sub' => ' giangpt ',
                'upn' => 'giangpt',
            ]),
        ]);

        $provider = app(SsoDanangProvider::class);
        $userinfo = $provider->getUserinfo(['code' => 'auth-code-xyz']);

        $this->assertSame('giangpt@danang.gov.vn', $userinfo['email']);
        $this->assertSame('Phan Tấn Giang', $userinfo['name']);
        $this->assertSame(' giangpt ', $userinfo['sub']);
        $this->assertIsArray($userinfo['raw']);
        $this->assertSame('giangpt', $userinfo['raw']['upn']);

        Http::assertSent(function ($req) {
            return $req->url() === 'https://sso.example.test/oauth2/token'
                && $req['grant_type'] === 'authorization_code'
                && $req['code'] === 'auth-code-xyz'
                && $req['client_id'] === 'test-client'
                && $req['client_secret'] === 'test-secret';
        });
    }

    public function test_throws_when_token_exchange_fails(): void
    {
        Http::fake([
            'https://sso.example.test/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SSO Đà Nẵng token exchange failed');

        app(SsoDanangProvider::class)->getUserinfo(['code' => 'bad-code']);
    }
}
