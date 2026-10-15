<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

interface SkuSequence
{
    /** Siguiente SKU atómico para el prefijo de la categoría raíz, p. ej. LIB-000231. */
    public function next(string $categoryRootCode): string;
}
