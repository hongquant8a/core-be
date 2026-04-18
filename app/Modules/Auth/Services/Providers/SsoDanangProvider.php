<?php

namespace App\Modules\Auth\Services\Providers;

use App\Modules\Core\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SsoDanangProvider implements SsoProvider
{
    public function key(): string
    {
        return 'sso_danang';
    }

    public function getUserinfo(array $payload): array
    {
        $code = $payload['code'] ?? null;
        if (! $code) {
            throw new RuntimeException('Missing authorization code.');
        }

        $baseUrl = rtrim((string) Setting::get('sso_danang_base_url'), '/');
        $clientId = Setting::get('sso_danang_client_id');
        $clientSecret = Setting::get('sso_danang_client_secret');
        $redirectUri = Setting::get('sso_danang_redirect_uri');

        if (! $baseUrl || ! $clientId || ! $clientSecret || ! $redirectUri) {
            throw new RuntimeException('Cấu hình SSO Đà Nẵng không đầy đủ.');
        }

        $tokenResp = Http::asForm()->post($baseUrl.'/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (! $tokenResp->successful()) {
            throw new RuntimeException('SSO Đà Nẵng token exchange failed: '.$tokenResp->body());
        }

        $accessToken = $tokenResp->json('access_token');
        if (! $accessToken) {
            throw new RuntimeException('SSO Đà Nẵng did not return access_token.');
        }

        $userResp = Http::withToken($accessToken)->get($baseUrl.'/oauth2/userinfo');

        if (! $userResp->successful()) {
            throw new RuntimeException('SSO Đà Nẵng userinfo fetch failed: '.$userResp->body());
        }

        $raw = $userResp->json();

        if (empty($raw['email']) || empty($raw['sub'])) {
            throw new RuntimeException('SSO Đà Nẵng userinfo thiếu email hoặc sub.');
        }

        return [
            'email' => (string) $raw['email'],
            'name' => (string) ($raw['name'] ?? ''),
            'sub' => (string) $raw['sub'],
            'raw' => $raw,
        ];
    }
}
