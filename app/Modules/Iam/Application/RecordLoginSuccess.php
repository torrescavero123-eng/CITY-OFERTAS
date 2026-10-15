<?php

declare(strict_types=1);

namespace App\Modules\Iam\Application;

use App\Modules\Iam\Domain\LockoutState;
use App\Modules\Iam\Domain\UserRepository;
use App\Shared\Domain\Clock;

final class RecordLoginSuccess
{
    public function __construct(private readonly UserRepository $users, private readonly Clock $clock) {}

    public function __invoke(int $userId): void
    {
        $this->users->saveLockoutState($userId, new LockoutState(0, null));
        $this->users->markLogin($userId, $this->clock->now());
    }
}
