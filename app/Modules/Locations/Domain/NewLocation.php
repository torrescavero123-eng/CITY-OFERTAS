<?php

declare(strict_types=1);

namespace App\Modules\Locations\Domain;

final readonly class NewLocation
{
    public function __construct(
        public string $code,
        public string $zone,
        public string $aisle,
        public string $rack,
        public int $level,
        public ?string $maxCapacity = null,
    ) {}
}
