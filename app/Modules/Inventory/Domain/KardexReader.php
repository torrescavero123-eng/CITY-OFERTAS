<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

interface KardexReader
{
    public function read(KardexQuery $query): KardexPage;
}
