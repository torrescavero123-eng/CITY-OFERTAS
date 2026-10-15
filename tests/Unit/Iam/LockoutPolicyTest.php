<?php

declare(strict_types=1);

use App\Modules\Iam\Domain\LockoutPolicy;
use App\Modules\Iam\Domain\LockoutState;

it('cuenta los fallos y bloquea al quinto por 15 minutos', function () {
    $now = new DateTimeImmutable('2026-10-10 12:00:00 UTC');
    $state = new LockoutState(0, null);

    for ($i = 1; $i <= 4; $i++) {
        $state = LockoutPolicy::afterFailure($state, $now);
        expect($state->failedAttempts)->toBe($i);
        expect(LockoutPolicy::isLocked($state, $now))->toBeFalse();
    }

    $state = LockoutPolicy::afterFailure($state, $now);
    expect(LockoutPolicy::isLocked($state, $now))->toBeTrue();
    expect($state->failedAttempts)->toBe(0);
    expect($state->lockedUntil?->format('H:i'))->toBe('12:15');
});

it('el bloqueo expira solo', function () {
    $locked = new LockoutState(0, new DateTimeImmutable('2026-10-10 12:15:00 UTC'));

    expect(LockoutPolicy::isLocked($locked, new DateTimeImmutable('2026-10-10 12:14:59 UTC')))->toBeTrue();
    expect(LockoutPolicy::isLocked($locked, new DateTimeImmutable('2026-10-10 12:15:00 UTC')))->toBeFalse();
});
