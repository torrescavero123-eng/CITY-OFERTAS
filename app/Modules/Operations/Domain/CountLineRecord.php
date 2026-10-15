<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

use App\Modules\Inventory\Domain\Quantity;

final readonly class CountLineRecord
{
    public function __construct(
        public int $id,
        public int $productId,
        public int $locationId,
        public Quantity $expected,
        public ?Quantity $counted = null,
        public bool $recountRequested = false,
        public ?int $countedBy = null,
        public ?int $adjustmentId = null,
    ) {}

    /** contado - esperado (puede ser negativo); null si aún no se cuenta. */
    public function difference(): ?string
    {
        return $this->counted === null ? null : bcsub($this->counted->value, $this->expected->value, 3);
    }

    public function hasDifference(): bool
    {
        $d = $this->difference();

        return $d !== null && bccomp($d, '0', 3) !== 0;
    }
}
