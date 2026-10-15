<?php

declare(strict_types=1);

namespace App\Modules\Locations\Domain;

final readonly class LocationRecord
{
    public function __construct(
        public int $id,
        public string $code,
        public bool $isStaging,
        public bool $isFrozen,
        public bool $isActive,
    ) {}
}
