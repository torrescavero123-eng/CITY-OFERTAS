<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

enum AdjustmentKind: string
{
    case Positive = 'POSITIVE';
    case Negative = 'NEGATIVE';
    case WriteOff = 'WRITE_OFF';

    public function movementKind(): MovementKind
    {
        return match ($this) {
            self::Positive => MovementKind::AdjustmentIn,
            self::Negative => MovementKind::AdjustmentOut,
            self::WriteOff => MovementKind::WriteOff,
        };
    }

    /** Tipo de movimiento que deben tener los motivos de este ajuste. */
    public function reasonMovementType(): string
    {
        return match ($this) {
            self::Positive => 'AJUSTE_POSITIVO',
            self::Negative => 'AJUSTE_NEGATIVO',
            self::WriteOff => 'MERMA',
        };
    }
}
