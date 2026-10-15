<?php

declare(strict_types=1);

namespace App\Modules\Iam\Application;

use App\Modules\Iam\Domain\LockoutPolicy;
use App\Modules\Iam\Domain\LockoutState;
use App\Modules\Iam\Domain\UserRepository;
use App\Shared\Domain\Clock;

final class RecordLoginFailure
{
    public function __construct(private readonly UserRepository $users, private readonly Clock $clock) {}

    public function __invoke(int $userId): LockoutState
    {
        $next = LockoutPolicy::afterFailure($this->users->lockoutState($userId), $this->clock->now());
        $this->users->saveLockoutState($userId, $next);

        return $next;
    }
}
