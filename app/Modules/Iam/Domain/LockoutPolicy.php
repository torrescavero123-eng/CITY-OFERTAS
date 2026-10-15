<?php

declare(strict_types=1);

namespace App\Modules\Iam\Domain;

/** 5 intentos fallidos => bloqueo de 15 minutos. */
final class LockoutPolicy
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public static function afterFailure(LockoutState $current, \DateTimeImmutable $now): LockoutState
    {
        $attempts = $current->failedAttempts + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            return new LockoutState(0, $now->modify('+'.self::LOCK_MINUTES.' minutes'));
        }

        return new LockoutState($attempts, $current->lockedUntil);
    }

    public static function isLocked(LockoutState $state, \DateTimeImmutable $now): bool
    {
        return $state->lockedUntil !== null && $state->lockedUntil > $now;
    }
}
