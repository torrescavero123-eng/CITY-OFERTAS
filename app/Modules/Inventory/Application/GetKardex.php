<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

use App\Modules\Inventory\Domain\KardexPage;
use App\Modules\Inventory\Domain\KardexQuery;
use App\Modules\Inventory\Domain\KardexReader;

/** Kardex de un producto: por almacén, por ubicación o total, con saldo inicial, saldo corrido y saldo final. */
final class GetKardex
{
    public function __construct(private readonly KardexReader $reader) {}

    public function __invoke(KardexQuery $query): KardexPage
    {
        return $this->reader->read($query);
    }
}
