<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transfers;

use App\Filament\Support\FormInput;
use App\Modules\Inventory\Domain\Exceptions\InventoryException;
use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Domain\NewTransfer;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\TransferLine;

final class TransferFormMapper
{
    /** @param array<string, mixed> $data */
    public static function toNewTransfer(array $data, int $userId): NewTransfer
    {
        try {
            return new NewTransfer(
                $userId,
                array_map(static fn (array $row): TransferLine => new TransferLine(
                    FormInput::requiredInt($row['product_id'] ?? null, 'el producto'),
                    FormInput::requiredInt($row['from_location_id'] ?? null, 'la ubicación de origen'),
                    FormInput::requiredInt($row['to_location_id'] ?? null, 'la ubicación de destino'),
                    Quantity::of(FormInput::requiredDecimal($row['quantity'] ?? null, 'la cantidad')),
                ), FormInput::rows($data['lines'] ?? null)),
                FormInput::nullableString($data['notes'] ?? null),
            );
        } catch (InventoryException $e) {
            throw OperationsException::invalid($e->getMessage());
        }
    }
}
