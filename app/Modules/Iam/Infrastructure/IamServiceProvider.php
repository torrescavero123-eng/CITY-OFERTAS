<?php

declare(strict_types=1);

namespace App\Modules\Iam\Infrastructure;

use App\Modules\Iam\Domain\PasswordHasher;
use App\Modules\Iam\Domain\UserRepository;
use Illuminate\Support\ServiceProvider;

final class IamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, DatabaseUserRepository::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
    }
}
