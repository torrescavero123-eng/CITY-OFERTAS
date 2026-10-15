<?php

declare(strict_types=1);

namespace App\Modules\Iam\Domain;

final readonly class LockoutState
{
    public function __construct(public int $failedAttempts, public ?\DateTimeImmutable $lockedUntil) {}
}
