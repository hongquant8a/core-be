<?php

namespace App\Services\Notification\Services;

use App\Services\Notification\Contracts\ContentBuilder;
use RuntimeException;

class ContentBuilderRegistry
{
    /** @var array<string, ContentBuilder> */
    private array $builders = [];

    public function register(string $eventKey, ContentBuilder $builder): void
    {
        $this->builders[$eventKey] = $builder;
    }

    public function for(string $eventKey): ContentBuilder
    {
        if (! isset($this->builders[$eventKey])) {
            throw new RuntimeException("No ContentBuilder registered for event: {$eventKey}");
        }

        return $this->builders[$eventKey];
    }
}
