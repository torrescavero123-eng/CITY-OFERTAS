<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

use App\Modules\Inventory\Domain\Quantity;

/** Stock esperado de un producto en una ubicación al iniciar el conteo. */
final readonly class CountBalance
{
    public function __construct(public int $productId, public int $locationId, public Quantity $quantity) {}
}
