<?php

declare(strict_types=1);

namespace App\Modules\Locations\Infrastructure;

use App\Modules\Locations\Domain\LocationRepository;
use Illuminate\Support\ServiceProvider;

final class LocationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LocationRepository::class, DatabaseLocationRepository::class);
    }
}
