<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

final readonly class ProductRecord
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public ?string $description,
        public int $categoryId,
        public ?int $brandId,
        public ?string $referencePrice,
        public ProductStatus $status,
        public int $version,
    ) {}
}
