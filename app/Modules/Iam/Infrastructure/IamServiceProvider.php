<?php

declare(strict_types=1);

namespace App\Modules\Iam\Infrastructure;

use App\Modules\Iam\Application\RecordLoginFailure;
use App\Modules\Iam\Application\RecordLoginSuccess;
use App\Modules\Iam\Domain\PasswordHasher;
use App\Modules\Iam\Domain\UserRepository;
use App\Shared\Domain\Clock;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class IamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, DatabaseUserRepository::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
    }

    public function boot(): void
    {
        Event::listen(Failed::class, function (Failed $event): void {
            if ($event->user !== null) {
                $this->app->make(RecordLoginFailure::class)((int) $event->user->getAuthIdentifier());
            }
        });

        Event::listen(Login::class, function (Login $event): void {
            $userId = (int) $event->user->getAuthIdentifier();
            $state = $this->app->make(UserRepository::class)->lockoutState($userId);

            if ($state->lockedUntil !== null && $state->lockedUntil > $this->app->make(Clock::class)->now()) {
                return;
            }

            $this->app->make(RecordLoginSuccess::class)($userId);
        });
    }
}
