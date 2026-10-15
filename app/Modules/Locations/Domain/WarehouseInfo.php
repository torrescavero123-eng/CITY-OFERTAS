<?php

declare(strict_types=1);

namespace App\Modules\Locations\Domain;

final readonly class WarehouseInfo
{
    public function __construct(public int $id, public string $code, public bool $isActive) {}
}
