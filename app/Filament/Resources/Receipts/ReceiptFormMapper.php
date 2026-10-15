<?php

declare(strict_types=1);

namespace App\Filament\Resources\Receipts;

use App\Filament\Support\FormInput;
use App\Modules\Inventory\Domain\Exceptions\InventoryException;
use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Inventory\Domain\UnitCost;
use App\Modules\Operations\Domain\DocumentType;
use App\Modules\Operations\Domain\NewReceipt;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\ReceiptLine;

final class ReceiptFormMapper
{
    /** @param array<string, mixed> $data */
    public static function toNewReceipt(array $data, int $userId): NewReceipt
    {
        try {
            $lines = array_map(static fn (array $row): ReceiptLine => new ReceiptLine(
                FormInput::requiredInt($row['product_id'] ?? null, 'el producto'),
                FormInput::requiredInt($row['location_id'] ?? null, 'la ubicación'),
                Quantity::of(FormInput::requiredDecimal($row['qty_received'] ?? null, 'la cantidad recibida')),
                UnitCost::of(FormInput::requiredDecimal($row['unit_cost'] ?? null, 'el costo unitario')),
                FormInput::nullableString($row['qty_expected'] ?? null) === null ? null : Quantity::of(FormInput::string($row['qty_expected'])),
            ), FormInput::rows($data['lines'] ?? null));

            return new NewReceipt(
                supplierId: FormInput::requiredInt($data['supplier_id'] ?? null, 'el proveedor'),
                warehouseId: FormInput::requiredInt($data['warehouse_id'] ?? null, 'el almacén'),
                docType: DocumentType::tryFrom(FormInput::string($data['doc_type'] ?? null)) ?? throw OperationsException::invalid('Seleccione el tipo de documento.'),
                docNumber: FormInput::string($data['doc_number'] ?? null),
                docDate: substr(FormInput::string($data['doc_date'] ?? null), 0, 10),
                receivedBy: $userId,
                lines: $lines,
                notes: FormInput::nullableString($data['notes'] ?? null),
            );
        } catch (InventoryException $e) {
            throw OperationsException::invalid($e->getMessage());
        }
    }
}
