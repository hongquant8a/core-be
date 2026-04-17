<?php

namespace Tests\Unit\Services\Notification\Services;

use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\Services\ContentBuilderRegistry;
use PHPUnit\Framework\TestCase;

class ContentBuilderRegistryTest extends TestCase
{
    public function test_register_and_resolve_builder(): void
    {
        $registry = new ContentBuilderRegistry;
        $builder = $this->createMock(ContentBuilder::class);

        $registry->register('document_issued', $builder);

        $this->assertSame($builder, $registry->for('document_issued'));
    }

    public function test_throws_when_not_registered(): void
    {
        $registry = new ContentBuilderRegistry;
        $this->expectException(\RuntimeException::class);
        $registry->for('nonexistent');
    }
}
