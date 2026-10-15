<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

final readonly class SimilarProduct
{
    public function __construct(public int $id, public string $sku, public string $name, public float $similarity) {}
}
