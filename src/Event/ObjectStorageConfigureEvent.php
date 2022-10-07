<?php

declare(strict_types=1);

namespace mglaman\DrupalObjectStorage\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ObjectStorageConfigureEvent extends Event
{
    /**
     * @param array<string, mixed> $args
     */
    public function __construct(
        private array $args
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * @param array<string, mixed> $args
     */
    public function setArgs(array $args): void
    {
        $this->args = $args;
    }
}
