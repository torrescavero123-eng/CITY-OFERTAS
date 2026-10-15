<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Infrastructure;

use App\Modules\Suppliers\Domain\SupplierRepository;
use Illuminate\Support\ServiceProvider;

final class SuppliersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SupplierRepository::class, DatabaseSupplierRepository::class);
    }
}
