<?php

namespace App\Modules\Auth\Services\Providers;

use App\Modules\Core\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CbccvcProvider implements SsoProvider
{
    public function key(): string
    {
        return 'cbccvc';
    }

    public function getUserinfo(array $payload): array
    {
        $username = $payload['username'] ?? null;
        $password = $payload['password'] ?? null;
        if (! $username || ! $password) {
            throw new RuntimeException('Missing CBCCVC credentials.');
        }

        $baseUrl = rtrim((string) Setting::get('sso_cbccvc_base_url'), '/');
        if (! $baseUrl) {
            throw new RuntimeException('Cấu hình CBCCVC không đầy đủ.');
        }

        $resp = Http::asForm()->post(
            $baseUrl.'/index.php?option=com_api&controller=core&task=login',
            ['username' => $username, 'password' => $password]
        );

        if ($resp->status() === 401) {
            throw new RuntimeException('CBCCVC login failed: invalid credentials.');
        }

        if (! $resp->successful()) {
            throw new RuntimeException('CBCCVC login failed: '.$resp->body());
        }

        $user = $resp->json('data.user');

        if (! is_array($user) || empty($user['id']) || empty($user['email'])) {
            throw new RuntimeException('CBCCVC response thiếu field user.');
        }

        return [
            'email' => (string) $user['email'],
            'name' => (string) ($user['fullname'] ?? $user['name'] ?? ''),
            'sub' => (string) $user['id'],
            'raw' => $resp->json('data'),
        ];
    }
}
