<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Application\ApproveAdjustment;
use App\Modules\Operations\Application\RecordDirectAdjustment;
use App\Modules\Operations\Application\RejectAdjustment;
use App\Modules\Operations\Application\RequestAdjustment;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\AdjustmentStatus;
use App\Modules\Operations\Domain\MovementKind;
use App\Modules\Operations\Domain\NewAdjustment;
use App\Modules\Operations\Domain\OperationsException;
use Tests\Support\Operations\OperationsKit;

function adjustmentUseCases(OperationsKit $k): array
{
    return [
        new RequestAdjustment($k->store, $k->store, $k->store, $k->tx, $k->audit),
        new ApproveAdjustment($k->store, $k->movements, $k->tx, $k->audit),
        new RejectAdjustment($k->store, $k->tx, $k->audit),
        new RecordDirectAdjustment($k->store, $k->movements, $k->tx, $k->audit),
    ];
}

function adj(AdjustmentKind $kind = AdjustmentKind::WriteOff, string $qty = '2', int $reason = 1, int $by = 7, int $product = 5): NewAdjustment
{
    return new NewAdjustment($kind, $product, 10, Quantity::of($qty), $reason, 'se mojaron en la bodega', $by);
}

it('solicitar no toca el stock y deja la solicitud pendiente', function () {
    $k = new OperationsKit;
    [$request] = adjustmentUseCases($k);

    $r = $request(adj());

    expect($r['code'])->toBe('AJU-2026-000001');
    expect($k->store->adjustments[$r['id']]['status'])->toBe(AdjustmentStatus::Pending);
    expect($k->movements->requests)->toHaveCount(0);
    expect($k->audit->entries[0]->action)->toBe('adjustment.requested');
});

it('valida motivo, tipo, ubicación y producto al solicitar', function () {
    $k = new OperationsKit;
    [$request] = adjustmentUseCases($k);

    expect(fn () => $request(adj(reason: 99)))->toThrow(OperationsException::class, 'motivo');
    expect(fn () => $request(adj(reason: 4)))->toThrow(OperationsException::class, 'desactivado');
    expect(fn () => $request(adj(AdjustmentKind::Positive, reason: 1)))->toThrow(OperationsException::class, 'no corresponde');
    expect(fn () => $request(adj(product: 999)))->toThrow(OperationsException::class, 'no existe');
    expect(fn () => $request(new NewAdjustment(AdjustmentKind::WriteOff, 5, 999, Quantity::of('1'), 1, 'se mojaron', 7)))->toThrow(OperationsException::class, 'no existe');
});

it('otra persona aprueba y se registra el movimiento con quién lo pidió y quién lo aprobó', function () {
    $k = new OperationsKit;
    [$request, $approve] = adjustmentUseCases($k);
    $id = $request(adj())['id'];

    $movementId = $approve($id, 9, 'visto bueno');

    $m = $k->movements->requests[0];
    expect($m->kind)->toBe(MovementKind::WriteOff);
    expect($m->fromLocationId)->toBe(10);
    expect($m->toLocationId)->toBeNull();
    expect($m->userId)->toBe(7);
    expect($m->approvedBy)->toBe(9);
    expect($m->reasonId)->toBe(1);
    expect($m->idempotencyKey)->toBe('AJU-2026-000001:APPROVE');
    expect($k->store->adjustments[$id]['status'])->toBe(AdjustmentStatus::Approved);
    expect($k->store->adjustments[$id]['movementId'])->toBe($movementId);
});

it('un ajuste positivo entra por la ubicación de destino', function () {
    $k = new OperationsKit;
    [$request, $approve] = adjustmentUseCases($k);
    $id = $request(adj(AdjustmentKind::Positive, '3', 3))['id'];

    $approve($id, 9);

    $m = $k->movements->requests[0];
    expect($m->kind)->toBe(MovementKind::AdjustmentIn);
    expect([$m->fromLocationId, $m->toLocationId])->toBe([null, 10]);
});

it('quien solicita no puede aprobar ni rechazar su propia solicitud', function () {
    $k = new OperationsKit;
    [$request, $approve, $reject] = adjustmentUseCases($k);
    $id = $request(adj(by: 7))['id'];

    expect(fn () => $approve($id, 7))->toThrow(OperationsException::class, 'otra persona');
    expect(fn () => $reject($id, 7, 'no procede'))->toThrow(OperationsException::class, 'otra persona');
    expect($k->movements->requests)->toHaveCount(0);
});

it('aprobar dos veces es idempotente y no duplica el movimiento', function () {
    $k = new OperationsKit;
    [$request, $approve] = adjustmentUseCases($k);
    $id = $request(adj())['id'];

    $first = $approve($id, 9);
    $again = $approve($id, 9);

    expect($again)->toBe($first);
    expect($k->movements->requests)->toHaveCount(1);
});

it('si el almacén rechaza el ajuste, la solicitud sigue pendiente', function () {
    $k = new OperationsKit;
    [$request, $approve] = adjustmentUseCases($k);
    $id = $request(adj(AdjustmentKind::Negative, '50', 2))['id'];
    $k->movements->rejectProducts = [5];

    expect(fn () => $approve($id, 9))->toThrow(OperationsException::class, 'Stock insuficiente');

    expect($k->store->adjustments[$id]['status'])->toBe(AdjustmentStatus::Pending);
});

it('rechazar exige explicación y bloquea la aprobación posterior', function () {
    $k = new OperationsKit;
    [$request, $approve, $reject] = adjustmentUseCases($k);
    $id = $request(adj())['id'];

    expect(fn () => $reject($id, 9, 'no'))->toThrow(OperationsException::class, 'Explique');
    $reject($id, 9, 'ya se había descontado ayer');
    $reject($id, 9, 'ya se había descontado ayer'); // idempotente

    expect($k->store->adjustments[$id]['status'])->toBe(AdjustmentStatus::Rejected);
    expect(fn () => $approve($id, 9))->toThrow(OperationsException::class, 'rechazada');
    expect($k->movements->requests)->toHaveCount(0);
});

it('el registro directo solo funciona dentro del límite configurado', function () {
    $k = new OperationsKit;
    [, , , $direct] = adjustmentUseCases($k);

    // límite 0 = deshabilitado
    expect(fn () => $direct(adj(by: 9), 'DIRECT-0001-ABCD'))->toThrow(OperationsException::class, 'límite');

    $k->store->directLimit = '50.00';
    // 2 unidades x S/ 2.00 = S/ 4.00 <= 50
    $id = $direct(adj(by: 9), 'DIRECT-0001-ABCD');
    expect($id)->toBe(1);
    expect($k->movements->requests[0]->approvedBy)->toBe(9);
    expect($k->movements->requests[0]->documentRef)->toBe('DIRECTO');
    expect($k->audit->entries[count($k->audit->entries) - 1]->action)->toBe('adjustment.direct');

    // 6 x S/ 10.00 = S/ 60.00 > 50
    expect(fn () => $direct(adj(qty: '6', by: 9, product: 6), 'DIRECT-0002-ABCD'))->toThrow(OperationsException::class, 'S/ 60.00');
    // mismo registro reintentado: no duplica
    $direct(adj(by: 9), 'DIRECT-0001-ABCD');
    expect($k->movements->requests)->toHaveCount(1);
});
