<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

interface SalesTargets
{
    /** Busca por SKU o código de barras; null si no existe o no tiene ubicación en el piso de venta. */
    public function resolve(string $code): ?SalesTarget;
}
