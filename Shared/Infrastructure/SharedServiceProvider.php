<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Domain\Clock;
use App\Shared\Domain\Transaction;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Transaction::class, LaravelTransaction::class);
        $this->app->bind(Clock::class, SystemClock::class);
    }
}
