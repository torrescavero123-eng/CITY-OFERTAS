<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class ReasonInfo
{
    public function __construct(
        public int $id,
        /** AJUSTE_POSITIVO | AJUSTE_NEGATIVO | MERMA */
        public string $movementType,
        public bool $requiresNotes,
        public bool $isActive,
    ) {}
}
