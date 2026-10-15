<?php

declare(strict_types=1);

namespace App\Filament\Resources\Adjustments;

use App\Filament\Support\FormInput;
use App\Modules\Inventory\Domain\Exceptions\InventoryException;
use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\NewAdjustment;
use App\Modules\Operations\Domain\OperationsException;

final class AdjustmentFormMapper
{
    /** @param array<string, mixed> $data */
    public static function toNewAdjustment(array $data, int $userId): NewAdjustment
    {
        try {
            return new NewAdjustment(
                AdjustmentKind::tryFrom(FormInput::string($data['kind'] ?? null)) ?? throw OperationsException::invalid('Seleccione el tipo de ajuste.'),
                FormInput::requiredInt($data['product_id'] ?? null, 'el producto'),
                FormInput::requiredInt($data['location_id'] ?? null, 'la ubicación'),
                Quantity::of(FormInput::requiredDecimal($data['quantity'] ?? null, 'la cantidad')),
                FormInput::requiredInt($data['reason_id'] ?? null, 'el motivo'),
                FormInput::string($data['notes'] ?? null),
                $userId,
            );
        } catch (InventoryException $e) {
            throw OperationsException::invalid($e->getMessage());
        }
    }
}
