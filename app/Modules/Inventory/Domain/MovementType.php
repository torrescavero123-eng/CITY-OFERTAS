<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

/**
 * Tipos de movimiento del ledger. Los valores coinciden con el CHECK de la BD.
 */
enum MovementType: string
{
    case OpeningBalance = 'SALDO_INICIAL';
    case Purchase = 'ENTRADA_COMPRA';
    case CustomerReturn = 'DEVOLUCION_CLIENTE';
    case AdjustmentIn = 'AJUSTE_POSITIVO';
    case Sale = 'SALIDA_VENTA';
    case WriteOff = 'MERMA';
    case AdjustmentOut = 'AJUSTE_NEGATIVO';
    case SupplierReturn = 'DEVOLUCION_PROVEEDOR';
    case Transfer = 'TRANSFERENCIA';

    public function isEntry(): bool
    {
        return match ($this) {
            self::OpeningBalance, self::Purchase, self::CustomerReturn, self::AdjustmentIn => true,
            default => false,
        };
    }

    public function isExit(): bool
    {
        return match ($this) {
            self::Sale, self::WriteOff, self::AdjustmentOut, self::SupplierReturn => true,
            default => false,
        };
    }

    public function isTransfer(): bool
    {
        return $this === self::Transfer;
    }

    public function requiresReason(): bool
    {
        return match ($this) {
            self::WriteOff, self::AdjustmentIn, self::AdjustmentOut => true,
            default => false,
        };
    }

    /** Solo las entradas con costo de compra recalculan el promedio ponderado. */
    public function requiresCost(): bool
    {
        return $this === self::Purchase || $this === self::OpeningBalance;
    }

    /** Permiso (spatie) necesario para registrar este tipo de movimiento directamente. */
    public function requiredPermission(): string
    {
        return match ($this) {
            self::OpeningBalance => 'inventory.opening_balance',
            self::Purchase, self::CustomerReturn, self::SupplierReturn => 'inventory.receive',
            self::Transfer => 'inventory.transfer',
            self::Sale => 'pos.import',
            self::WriteOff, self::AdjustmentIn, self::AdjustmentOut => 'inventory.adjust.approve',
        };
    }
}
