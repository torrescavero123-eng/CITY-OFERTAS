<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

final readonly class KardexRow
{
    public function __construct(
        public int $movementId,
        public string $at,
        public string $type,
        /** Positivo = entra a la ubicación; negativo = sale. */
        public string $delta,
        public string $runningBalance,
        public string $locationCode,
        public ?string $documentRef,
        public ?string $notes,
        public ?string $unitCost,
        public string $userName,
    ) {}
}
