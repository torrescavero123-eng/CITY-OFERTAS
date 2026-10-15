<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Events;

use App\Modules\Inventory\Domain\MovementType;

/** Se publica DESPUÉS del commit. Alerts reacciona a este evento. */
final readonly class StockChanged
{
    /** @param list<int> $locationIds */
    public function __construct(
        public int $movementId,
        public int $productId,
        public MovementType $type,
        public array $locationIds,
    ) {}
}
