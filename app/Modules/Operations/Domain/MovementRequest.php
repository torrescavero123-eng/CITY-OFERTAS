<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Inventory\Domain\UnitCost;

/** Pedido de movimiento de stock. La clave de idempotencia hace seguro reintentar cualquier operación. */
final readonly class MovementRequest
{
    public function __construct(
        public MovementKind $kind,
        public int $productId,
        public Quantity $quantity,
        public int $userId,
        public string $idempotencyKey,
        public ?int $fromLocationId = null,
        public ?int $toLocationId = null,
        public ?UnitCost $unitCost = null,
        public ?int $reasonId = null,
        public ?int $presentationId = null,
        public ?Quantity $presentationQty = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $documentRef = null,
        public ?string $notes = null,
        public ?int $approvedBy = null,
    ) {}
}
