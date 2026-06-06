<?php

namespace App\Modules\Auth\Services\Providers;

interface SsoProvider
{
    /**
     * Nhận payload từ client, gọi ra provider, trả về userinfo chuẩn hóa.
     *
     * @param  array  $payload  Provider-specific: ['code' => ...] hoặc ['username' => ..., 'password' => ...]
     * @return array{email: string, name: string, username: string, sub: string, raw: array}
     *
     * @throws \RuntimeException Khi provider trả lỗi hoặc payload không hợp lệ.
     */
    public function getUserinfo(array $payload): array;

    /**
     * Provider key (dùng để phân biệt provider: sso_danang, cbccvc, ...).
     */
    public function key(): string;
}
