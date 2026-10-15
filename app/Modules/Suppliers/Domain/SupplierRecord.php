<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Domain;

final readonly class SupplierRecord
{
    /** @param array<string, scalar|null> $data campos editables actuales */
    public function __construct(
        public int $id,
        public string $ruc,
        public string $businessName,
        public bool $isActive,
        public array $data = [],
    ) {}
}
