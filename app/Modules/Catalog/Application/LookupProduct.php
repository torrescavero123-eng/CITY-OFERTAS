<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\CatalogRepository;
use App\Modules\Catalog\Domain\ProductLookup;

/** Escaneo/digitación en la PWA: acepta código de barras o SKU. */
final class LookupProduct
{
    public function __construct(private readonly CatalogRepository $catalog) {}

    public function __invoke(string $code): ?ProductLookup
    {
        $code = trim($code);

        return $code === '' ? null : $this->catalog->lookup($code);
    }
}
