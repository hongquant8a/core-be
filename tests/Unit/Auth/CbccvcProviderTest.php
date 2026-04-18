<?php

namespace Tests\Unit\Auth;

use App\Modules\Auth\Services\Providers\CbccvcProvider;
use App\Modules\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CbccvcProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create([
            'key' => 'sso_cbccvc_base_url',
            'value' => 'https://cbccvc.example.test',
            'group' => 'sso_cbccvc',
            'is_public' => true,
            'type' => 'string',
            'label' => 'Base URL',
            'sort_order' => 2,
        ]);
    }

    public function test_logs_in_and_returns_userinfo(): void
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
                    'jwt' => 'jwt-token-here',
                ],
            ]),
        ]);

        $userinfo = app(CbccvcProvider::class)->getUserinfo([
            'username' => 'giangpt',
            'password' => 'secret',
        ]);

        $this->assertSame('giangpt@danang.gov.vn', $userinfo['email']);
        $this->assertSame('Phan Tấn Giang', $userinfo['name']);
        $this->assertSame('153208', $userinfo['sub']);
    }

    public function test_throws_when_credentials_invalid(): void
    {
        Http::fake([
            'https://cbccvc.example.test/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CBCCVC login failed');

        app(CbccvcProvider::class)->getUserinfo(['username' => 'x', 'password' => 'y']);
    }
}
