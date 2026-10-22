<?php

use App\Modules\Audit\Infrastructure\AuditServiceProvider;
use App\Modules\Catalog\Infrastructure\CatalogServiceProvider;
use App\Modules\Iam\Infrastructure\IamServiceProvider;
use App\Modules\Inventory\Infrastructure\InventoryServiceProvider;
use App\Modules\Locations\Infrastructure\LocationsServiceProvider;
use App\Modules\Operations\Infrastructure\OperationsServiceProvider;
use App\Modules\Suppliers\Infrastructure\SuppliersServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SharedServiceProvider::class,
    AuditServiceProvider::class,
    IamServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    LocationsServiceProvider::class,
    SuppliersServiceProvider::class,
    OperationsServiceProvider::class,
];
