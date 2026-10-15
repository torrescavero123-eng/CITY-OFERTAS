<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

final readonly class LocationState
{
    public function __construct(
        public int $id,
        public string $code,
        public bool $isActive,
        public bool $isFrozen,
    ) {}
}
