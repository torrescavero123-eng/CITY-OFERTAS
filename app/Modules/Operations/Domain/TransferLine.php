<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

use App\Modules\Inventory\Domain\Quantity;

final readonly class TransferLine
{
    public function __construct(
        public int $productId,
        public int $fromLocationId,
        public int $toLocationId,
        public Quantity $quantity,
    ) {
        if ($fromLocationId === $toLocationId) {
            throw OperationsException::invalid('El origen y el destino de una línea deben ser distintos.');
        }
        if (! $quantity->isPositive()) {
            throw OperationsException::invalid('La cantidad a transferir debe ser mayor que cero.');
        }
    }
}
