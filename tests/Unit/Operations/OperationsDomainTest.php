<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Inventory\Domain\UnitCost;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\DeterministicUuid;
use App\Modules\Operations\Domain\DocumentType;
use App\Modules\Operations\Domain\NewAdjustment;
use App\Modules\Operations\Domain\NewReceipt;
use App\Modules\Operations\Domain\NewTransfer;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\ReceiptLine;
use App\Modules\Operations\Domain\TransferLine;

function line(int $product = 5, int $loc = 10, string $qty = '10', string $cost = '2'): ReceiptLine
{
    return new ReceiptLine($product, $loc, Quantity::of($qty), UnitCost::of($cost));
}

it('el UUID derivado de la clave es estable y válido', function () {
    $a = DeterministicUuid::fromKey('REC-2026-000001:L1');
    $b = DeterministicUuid::fromKey('REC-2026-000001:L1');

    expect($a)->toBe($b);
    expect(DeterministicUuid::fromKey('REC-2026-000001:L2'))->not->toBe($a);
    expect(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a))->toBe(1);
});

it('convierte una recepción por presentación a unidad base', function () {
    // 5 cajas de 12, a S/ 24 la caja => 60 unidades a S/ 2.00 c/u
    $l = ReceiptLine::fromPresentation(5, 10, 7, Quantity::of('5'), '12', '24', Quantity::of('6'));

    expect($l->quantity->value)->toBe('60.000');
    expect($l->unitCost->value)->toBe('2.0000');
    expect($l->expected?->value)->toBe('72.000');
    expect($l->presentationId)->toBe(7);
    expect($l->presentationQty?->value)->toBe('5.000');
    expect($l->hasDiscrepancy())->toBeTrue();
});

it('redondea el costo por unidad base half-up a 4 decimales', function () {
    // S/ 10 la docena => 0.8333 por unidad
    expect(ReceiptLine::fromPresentation(5, 10, 7, Quantity::of('1'), '12', '10')->unitCost->value)->toBe('0.8333');
    expect(ReceiptLine::fromPresentation(5, 10, 7, Quantity::of('1'), '3', '10')->unitCost->value)->toBe('3.3333');
    expect(fn () => ReceiptLine::fromPresentation(5, 10, 7, Quantity::of('1'), '0', '10'))->toThrow(OperationsException::class, 'factor');
});

it('detecta diferencias solo si había cantidad esperada', function () {
    expect(line()->hasDiscrepancy())->toBeFalse();
    expect((new ReceiptLine(5, 10, Quantity::of('10'), UnitCost::of('2'), Quantity::of('10')))->hasDiscrepancy())->toBeFalse();
    expect((new ReceiptLine(5, 10, Quantity::of('9'), UnitCost::of('2'), Quantity::of('10')))->hasDiscrepancy())->toBeTrue();
    expect(fn () => new ReceiptLine(5, 10, Quantity::of('1'), UnitCost::of('2'), null, 7))->toThrow(OperationsException::class, 'juntas');
});

it('valida el documento de la recepción', function () {
    $ok = new NewReceipt(1, 1, DocumentType::Invoice, ' f001-234 ', '2026-10-09', 7, [line()], '  llegó completa ');

    expect($ok->docNumber)->toBe('F001-234');
    expect($ok->notes)->toBe('llegó completa');
    expect(fn () => new NewReceipt(1, 1, DocumentType::Invoice, '', '2026-10-09', 7, [line()]))->toThrow(OperationsException::class, 'número');
    expect(fn () => new NewReceipt(1, 1, DocumentType::Invoice, 'F001', '2026-13-40', 7, [line()]))->toThrow(OperationsException::class, 'fecha');
    expect(fn () => new NewReceipt(1, 1, DocumentType::Invoice, 'F001', '2026-10-09', 7, []))->toThrow(OperationsException::class, 'líneas');
});

it('valida las líneas de transferencia y los ajustes', function () {
    expect(fn () => new TransferLine(5, 10, 10, Quantity::of('1')))->toThrow(OperationsException::class, 'distintos');
    expect(fn () => new TransferLine(5, 10, 11, Quantity::zero()))->toThrow(OperationsException::class, 'mayor que cero');
    expect(fn () => new NewTransfer(7, []))->toThrow(OperationsException::class, 'líneas');

    expect(fn () => new NewAdjustment(AdjustmentKind::WriteOff, 5, 10, Quantity::of('1'), 1, 'ok', 7))->toThrow(OperationsException::class, 'Explique');
    expect(fn () => new NewAdjustment(AdjustmentKind::WriteOff, 5, 10, Quantity::zero(), 1, 'se mojaron', 7))->toThrow(OperationsException::class, 'mayor que cero');
});
