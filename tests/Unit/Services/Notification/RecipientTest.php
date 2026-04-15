<?php

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\DTOs\Recipient;
use PHPUnit\Framework\TestCase;

class RecipientTest extends TestCase
{
    public function test_constructs_with_all_nullable_fields_defaulting_to_null(): void
    {
        $r = new Recipient();
        $this->assertNull($r->phone);
        $this->assertNull($r->email);
        $this->assertNull($r->zaloId);
        $this->assertNull($r->name);
    }

    public function test_stores_provided_values(): void
    {
        $r = new Recipient(phone: '0905112233', email: 'a@b.c', zaloId: 'z1', name: 'Tuan');
        $this->assertSame('0905112233', $r->phone);
        $this->assertSame('a@b.c', $r->email);
        $this->assertSame('z1', $r->zaloId);
        $this->assertSame('Tuan', $r->name);
    }
}
