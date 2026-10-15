<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

/** A qué producto y ubicación del piso de venta corresponde un código de la caja. */
final readonly class SalesTarget
{
    public function __construct(
        public int $productId,
        public string $sku,
        public int $locationId,
        public string $locationCode,
        /** Stock disponible hoy en esa ubicación. */
        public string $available,
    ) {}
}
