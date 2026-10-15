<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\CatalogRepository;
use App\Modules\Catalog\Domain\NewProduct;
use App\Modules\Catalog\Domain\SimilarProduct;

/** Para advertir de posibles duplicados mientras se escribe el nombre (similitud >= 0.8). */
final class FindSimilarProducts
{
    public const THRESHOLD = 0.8;

    public function __construct(private readonly CatalogRepository $catalog) {}

    /** @return list<SimilarProduct> */
    public function __invoke(string $name, int $limit = 5): array
    {
        return $this->catalog->similarNames(NewProduct::normalizeName($name), self::THRESHOLD, $limit);
    }
}
