<?php

use App\Modules\Audit\Infrastructure\AuditServiceProvider;
use App\Modules\Catalog\Infrastructure\CatalogServiceProvider;
use App\Modules\Iam\Infrastructure\IamServiceProvider;
use App\Modules\Inventory\Infrastructure\InventoryServiceProvider;
use App\Providers\AppServiceProvider;
use App\Shared\Infrastructure\SharedServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    AuditServiceProvider::class,
    IamServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
];
