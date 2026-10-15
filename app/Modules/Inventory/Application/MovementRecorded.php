<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application;

final readonly class MovementRecorded
{
    public function __construct(
        public int $movementId,
        public string $uuid,
        /** true si era un reintento: el movimiento ya existía y no se duplicó. */
        public bool $duplicate,
    ) {}
}
