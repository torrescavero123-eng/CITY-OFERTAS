<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Inventory\Domain\UnitCost;
use App\Modules\Operations\Application\CancelReceipt;
use App\Modules\Operations\Application\ConfirmReceipt;
use App\Modules\Operations\Application\CreateReceiptDraft;
use App\Modules\Operations\Application\ReplaceReceiptItems;
use App\Modules\Operations\Domain\DocumentType;
use App\Modules\Operations\Domain\MovementKind;
use App\Modules\Operations\Domain\NewReceipt;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\ReceiptLine;
use App\Modules\Operations\Domain\ReceiptStatus;
use Tests\Support\Operations\OperationsKit;

function receiptUseCases(OperationsKit $k): array
{
    return [
        new CreateReceiptDraft($k->store, $k->store, $k->store, $k->tx, $k->audit, $k->clock),
        new ConfirmReceipt($k->store, $k->movements, $k->tx, $k->audit, $k->clock),
        new CancelReceipt($k->store, $k->tx, $k->audit),
        new ReplaceReceiptItems($k->store, $k->store, $k->tx),
    ];
}

function receiptLine(int $product, string $qty, string $cost = '2', ?string $expected = null, int $loc = 10): ReceiptLine
{
    return new ReceiptLine($product, $loc, Quantity::of($qty), UnitCost::of($cost), $expected === null ? null : Quantity::of($expected));
}

function draftReceipt(array $lines, string $number = 'F001-234', int $supplier = 1, int $warehouse = 1, string $date = '2026-10-09'): NewReceipt
{
    return new NewReceipt($supplier, $warehouse, DocumentType::Invoice, $number, $date, 7, $lines);
}

it('crea el borrador con código correlativo y lo audita', function () {
    $k = new OperationsKit;
    [$create] = receiptUseCases($k);

    $a = $create(draftReceipt([receiptLine(5, '10')]));
    $b = $create(draftReceipt([receiptLine(5, '10')], 'F001-235'));

    expect($a['code'])->toBe('REC-2026-000001');
    expect($b['code'])->toBe('REC-2026-000002');
    expect($k->store->receipts[$a['id']]['status'])->toBe(ReceiptStatus::Draft);
    expect($k->audit->entries[0]->action)->toBe('receipt.drafted');
    expect($k->movements->requests)->toHaveCount(0); // un borrador no toca el stock
});

it('rechaza proveedor inactivo, almacén inválido, ubicación ajena, fecha futura y factura repetida', function () {
    $k = new OperationsKit;
    [$create] = receiptUseCases($k);

    expect(fn () => $create(draftReceipt([receiptLine(5, '1')], 'F1', 2)))->toThrow(OperationsException::class, 'proveedor');
    expect(fn () => $create(draftReceipt([receiptLine(5, '1')], 'F1', 1, 9)))->toThrow(OperationsException::class, 'almacén');
    expect(fn () => $create(draftReceipt([receiptLine(5, '1', '2', null, 20)])))->toThrow(OperationsException::class, 'no pertenece');
    expect(fn () => $create(draftReceipt([receiptLine(5, '1')], 'F1', 1, 1, '2026-12-31')))->toThrow(OperationsException::class, 'futura');

    $create(draftReceipt([receiptLine(5, '1')]));
    expect(fn () => $create(draftReceipt([receiptLine(5, '1')])))->toThrow(OperationsException::class, 'Ya se registró');
});

it('una factura anulada permite volver a registrar el mismo documento', function () {
    $k = new OperationsKit;
    [$create, , $cancel] = receiptUseCases($k);

    $first = $create(draftReceipt([receiptLine(5, '1')]));
    $cancel($first['id'], 7);

    expect($create(draftReceipt([receiptLine(5, '1')]))['code'])->toBe('REC-2026-000002');
});

it('al confirmar registra una entrada por línea, ordenadas por producto, con clave estable', function () {
    $k = new OperationsKit;
    [$create, $confirm] = receiptUseCases($k);
    $id = $create(draftReceipt([receiptLine(6, '4', '10'), receiptLine(5, '10', '2', '12')]))['id'];

    $result = $confirm($id, 9);

    expect($result)->toBe(['movements' => 2, 'discrepancies' => 1]);
    expect($k->store->receipts[$id]['status'])->toBe(ReceiptStatus::Confirmed);
    expect(array_map(static fn ($r) => $r->productId, $k->movements->requests))->toBe([5, 6]);
    $first = $k->movements->requests[0];
    expect($first->kind)->toBe(MovementKind::Purchase);
    expect($first->toLocationId)->toBe(10);
    expect($first->unitCost?->value)->toBe('2.0000');
    expect($first->userId)->toBe(9);
    expect($first->documentRef)->toBe('FACTURA F001-234');
    expect($first->idempotencyKey)->toBe('REC-2026-000001:L2');
    expect($k->audit->entries[count($k->audit->entries) - 1]->action)->toBe('receipt.confirmed');
});

it('confirmar dos veces no registra nada más', function () {
    $k = new OperationsKit;
    [$create, $confirm] = receiptUseCases($k);
    $id = $create(draftReceipt([receiptLine(5, '10')]))['id'];

    $confirm($id, 9);
    $again = $confirm($id, 9);

    expect($again['movements'])->toBe(1);
    expect($k->movements->requests)->toHaveCount(1);
});

it('las líneas con cantidad cero no generan movimiento; si todas son cero no se confirma', function () {
    $k = new OperationsKit;
    [$create, $confirm] = receiptUseCases($k);
    $mixed = $create(draftReceipt([receiptLine(5, '0', '2', '5'), receiptLine(6, '3', '10')]))['id'];
    $zero = $create(draftReceipt([receiptLine(5, '0')], 'F001-300'))['id'];

    expect($confirm($mixed, 9))->toBe(['movements' => 1, 'discrepancies' => 1]);
    expect(fn () => $confirm($zero, 9))->toThrow(OperationsException::class, 'No hay cantidades');
});

it('si una línea es rechazada no entra NINGUNA y la recepción sigue en borrador', function () {
    $k = new OperationsKit;
    [$create, $confirm] = receiptUseCases($k);
    $id = $create(draftReceipt([receiptLine(5, '10'), receiptLine(6, '4', '10')]))['id'];
    $k->movements->rejectProducts = [6];

    expect(fn () => $confirm($id, 9))->toThrow(OperationsException::class, 'Stock insuficiente');

    expect($k->store->receipts[$id]['status'])->toBe(ReceiptStatus::Draft);
    expect($k->movements->requests)->toHaveCount(0);
    foreach ($k->store->receipts[$id]['items'] as $item) {
        expect($item['movementId'])->toBeNull();
    }

    $k->movements->rejectProducts = [];
    expect($confirm($id, 9)['movements'])->toBe(2); // reintento posterior funciona
});

it('solo se anula o modifica un borrador', function () {
    $k = new OperationsKit;
    [$create, $confirm, $cancel, $replace] = receiptUseCases($k);
    $draft = $create(draftReceipt([receiptLine(5, '10')]))['id'];

    $replace($draft, [receiptLine(5, '20'), receiptLine(6, '1', '10')]);
    expect($k->store->receipts[$draft]['items'])->toHaveCount(2);
    expect(fn () => $replace($draft, []))->toThrow(OperationsException::class, 'líneas');
    expect(fn () => $replace($draft, [receiptLine(5, '1', '2', null, 20)]))->toThrow(OperationsException::class, 'no pertenece');

    $confirm($draft, 9);
    expect(fn () => $cancel($draft, 9))->toThrow(OperationsException::class, 'confirmada');
    expect(fn () => $replace($draft, [receiptLine(5, '1')]))->toThrow(OperationsException::class, 'borrador');
    expect(fn () => $confirm(404, 9))->toThrow(OperationsException::class, 'no existe');
});
