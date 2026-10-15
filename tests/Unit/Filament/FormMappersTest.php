<?php

declare(strict_types=1);

use App\Filament\Resources\Adjustments\AdjustmentFormMapper;
use App\Filament\Resources\Receipts\ReceiptFormMapper;
use App\Filament\Resources\Transfers\TransferFormMapper;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\DocumentType;
use App\Modules\Operations\Domain\OperationsException;

function receiptData(array $over = []): array
{
    return $over + [
        'supplier_id' => '3', 'warehouse_id' => '1', 'doc_type' => 'FACTURA', 'doc_number' => ' f001-234 ',
        'doc_date' => '2026-10-09 00:00:00', 'notes' => '',
        'lines' => [
            'uuid-1' => ['product_id' => '5', 'location_id' => '10', 'qty_expected' => '12', 'qty_received' => '10.5', 'unit_cost' => '2.5'],
            'uuid-2' => ['product_id' => '6', 'location_id' => '10', 'qty_expected' => '', 'qty_received' => 4, 'unit_cost' => 1.25],
        ],
    ];
}

it('convierte el formulario de recepción en un documento del dominio', function () {
    $r = ReceiptFormMapper::toNewReceipt(receiptData(), 7);

    expect($r->supplierId)->toBe(3);
    expect($r->docType)->toBe(DocumentType::Invoice);
    expect($r->docNumber)->toBe('F001-234');
    expect($r->docDate)->toBe('2026-10-09');
    expect($r->receivedBy)->toBe(7);
    expect($r->notes)->toBeNull();
    expect($r->lines)->toHaveCount(2);
    expect($r->lines[0]->quantity->value)->toBe('10.500');
    expect($r->lines[0]->expected?->value)->toBe('12.000');
    expect($r->lines[0]->hasDiscrepancy())->toBeTrue();
    expect($r->lines[1]->unitCost->value)->toBe('1.2500');
    expect($r->lines[1]->expected)->toBeNull();
});

it('la recepción rechaza datos incompletos o inválidos con mensajes claros', function () {
    expect(fn () => ReceiptFormMapper::toNewReceipt(receiptData(['supplier_id' => '']), 7))->toThrow(OperationsException::class, 'el proveedor');
    expect(fn () => ReceiptFormMapper::toNewReceipt(receiptData(['doc_type' => 'XX']), 7))->toThrow(OperationsException::class, 'tipo de documento');
    expect(fn () => ReceiptFormMapper::toNewReceipt(receiptData(['lines' => []]), 7))->toThrow(OperationsException::class, 'líneas');
    expect(fn () => ReceiptFormMapper::toNewReceipt(receiptData(['lines' => [['product_id' => 5, 'location_id' => 10, 'qty_received' => '', 'unit_cost' => 1]]]), 7))->toThrow(OperationsException::class, 'cantidad recibida');
    expect(fn () => ReceiptFormMapper::toNewReceipt(receiptData(['lines' => [['product_id' => 5, 'location_id' => 10, 'qty_received' => '1.2345', 'unit_cost' => 1]]]), 7))->toThrow(OperationsException::class, 'Cantidad inválida');
    expect(fn () => ReceiptFormMapper::toNewReceipt(receiptData(['lines' => [['product_id' => 5, 'location_id' => 10, 'qty_received' => '1', 'unit_cost' => '-1']]]), 7))->toThrow(OperationsException::class, 'Costo');
});

it('convierte el formulario de transferencia y valida origen/destino', function () {
    $t = TransferFormMapper::toNewTransfer(['notes' => 'reposición piso', 'lines' => [['product_id' => '5', 'from_location_id' => '10', 'to_location_id' => '11', 'quantity' => '6']]], 7);

    expect($t->requestedBy)->toBe(7);
    expect($t->notes)->toBe('reposición piso');
    expect($t->lines[0]->quantity->value)->toBe('6.000');
    expect(fn () => TransferFormMapper::toNewTransfer(['lines' => [['product_id' => 5, 'from_location_id' => 10, 'to_location_id' => 10, 'quantity' => 1]]], 7))->toThrow(OperationsException::class, 'distintos');
    expect(fn () => TransferFormMapper::toNewTransfer(['lines' => [['product_id' => 5, 'from_location_id' => 10, 'to_location_id' => '', 'quantity' => 1]]], 7))->toThrow(OperationsException::class, 'destino');
    expect(fn () => TransferFormMapper::toNewTransfer(['lines' => []], 7))->toThrow(OperationsException::class, 'líneas');
});

it('convierte el formulario de ajuste y exige explicación', function () {
    $a = AdjustmentFormMapper::toNewAdjustment([
        'kind' => 'WRITE_OFF', 'product_id' => '5', 'location_id' => '10', 'quantity' => '2', 'reason_id' => '1', 'notes' => '  Se mojaron en la bodega ',
    ], 7);

    expect($a->kind)->toBe(AdjustmentKind::WriteOff);
    expect($a->quantity->value)->toBe('2.000');
    expect($a->notes)->toBe('Se mojaron en la bodega');
    expect($a->requestedBy)->toBe(7);

    $base = ['kind' => 'NEGATIVE', 'product_id' => 5, 'location_id' => 10, 'quantity' => 1, 'reason_id' => 2, 'notes' => 'conteo físico'];
    expect(fn () => AdjustmentFormMapper::toNewAdjustment(['kind' => ''] + $base, 7))->toThrow(OperationsException::class, 'tipo de ajuste');
    expect(fn () => AdjustmentFormMapper::toNewAdjustment(['reason_id' => ''] + $base, 7))->toThrow(OperationsException::class, 'motivo');
    expect(fn () => AdjustmentFormMapper::toNewAdjustment(['notes' => 'no'] + $base, 7))->toThrow(OperationsException::class, 'Explique');
    expect(fn () => AdjustmentFormMapper::toNewAdjustment(['quantity' => '0'] + $base, 7))->toThrow(OperationsException::class, 'mayor que cero');
});
