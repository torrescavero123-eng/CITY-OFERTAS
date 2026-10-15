<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

use App\Modules\Inventory\Domain\Quantity;

final readonly class NewAdjustment
{
    public string $notes;

    public function __construct(
        public AdjustmentKind $kind,
        public int $productId,
        public int $locationId,
        public Quantity $quantity,
        public int $reasonId,
        string $notes,
        public int $requestedBy,
        public ?string $evidencePath = null,
    ) {
        $this->notes = trim($notes);

        if (! $quantity->isPositive()) {
            throw OperationsException::invalid('La cantidad del ajuste debe ser mayor que cero.');
        }
        if (mb_strlen($this->notes) < 5 || mb_strlen($this->notes) > 500) {
            throw OperationsException::invalid('Explique el motivo del ajuste (entre 5 y 500 caracteres).');
        }
        if ($evidencePath !== null && mb_strlen($evidencePath) > 255) {
            throw OperationsException::invalid('La ruta de la evidencia es demasiado larga.');
        }
    }
}
