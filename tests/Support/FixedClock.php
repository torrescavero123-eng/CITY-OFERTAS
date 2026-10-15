<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Domain\Clock;

final class FixedClock implements Clock
{
    public function __construct(public \DateTimeImmutable $now = new \DateTimeImmutable('2026-10-10 12:00:00 UTC')) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
