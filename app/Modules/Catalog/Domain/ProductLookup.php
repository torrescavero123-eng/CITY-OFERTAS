<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

/** Resultado de escanear un código o digitar un SKU: producto, presentación y stock por ubicación. */
final readonly class ProductLookup
{
    /** @param list<array{location_id: int, location_code: string, warehouse: string, quantity: string}> $stock */
    public function __construct(
        public int $productId,
        public string $sku,
        public string $name,
        public string $status,
        public ?int $presentationId,
        public string $presentationFactor,
        public string $costAvg,
        public array $stock,
    ) {}
}
