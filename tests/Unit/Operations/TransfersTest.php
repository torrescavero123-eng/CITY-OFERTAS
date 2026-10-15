<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Application\CancelTransfer;
use App\Modules\Operations\Application\CompleteTransfer;
use App\Modules\Operations\Application\CreateTransferDraft;
use App\Modules\Operations\Domain\MovementKind;
use App\Modules\Operations\Domain\NewTransfer;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\TransferLine;
use App\Modules\Operations\Domain\TransferStatus;
use Tests\Support\Operations\OperationsKit;

function transferUseCases(OperationsKit $k): array
{
    return [
        new CreateTransferDraft($k->store, $k->store, $k->store, $k->tx, $k->audit),
        new CompleteTransfer($k->store, $k->movements, $k->tx, $k->audit, $k->clock),
        new CancelTransfer($k->store, $k->tx, $k->audit),
    ];
}

function tline(int $product, int $from, int $to, string $qty): TransferLine
{
    return new TransferLine($product, $from, $to, Quantity::of($qty));
}

it('completa la transferencia registrando un movimiento por línea, en orden fijo', function () {
    $k = new OperationsKit;
    [$create, $complete] = transferUseCases($k);
    $t = $create(new NewTransfer(7, [tline(6, 10, 11, '2'), tline(5, 10, 11, '5')]));

    expect($t['code'])->toBe('TRF-2026-000001');
    expect($complete($t['id'], 9))->toBe(['movements' => 2]);

    expect($k->store->transfers[$t['id']]['status'])->toBe(TransferStatus::Completed);
    expect(array_map(static fn ($r) => $r->productId, $k->movements->requests))->toBe([5, 6]);
    $m = $k->movements->requests[0];
    expect($m->kind)->toBe(MovementKind::Transfer);
    expect([$m->fromLocationId, $m->toLocationId])->toBe([10, 11]);
    expect($m->documentRef)->toBe('TRF-2026-000001');
});

it('completar dos veces no mueve nada de nuevo', function () {
    $k = new OperationsKit;
    [$create, $complete] = transferUseCases($k);
    $t = $create(new NewTransfer(7, [tline(5, 10, 11, '5')]));

    $complete($t['id'], 9);
    $complete($t['id'], 9);

    expect($k->movements->requests)->toHaveCount(1);
});

it('si una línea no tiene stock, no se mueve ninguna', function () {
    $k = new OperationsKit;
    [$create, $complete] = transferUseCases($k);
    $t = $create(new NewTransfer(7, [tline(5, 10, 11, '5'), tline(6, 10, 11, '2')]));
    $k->movements->rejectProducts = [6];

    expect(fn () => $complete($t['id'], 9))->toThrow(OperationsException::class, 'Stock insuficiente');

    expect($k->store->transfers[$t['id']]['status'])->toBe(TransferStatus::Draft);
    expect($k->movements->requests)->toHaveCount(0);
});

it('valida ubicaciones y permite anular solo borradores', function () {
    $k = new OperationsKit;
    [$create, $complete, $cancel] = transferUseCases($k);

    expect(fn () => $create(new NewTransfer(7, [tline(5, 10, 99, '1')])))->toThrow(OperationsException::class, 'no existe');

    $a = $create(new NewTransfer(7, [tline(5, 10, 11, '1')]));
    $cancel($a['id'], 7);
    $cancel($a['id'], 7);
    expect(fn () => $complete($a['id'], 9))->toThrow(OperationsException::class, 'anulada');

    $b = $create(new NewTransfer(7, [tline(5, 10, 11, '1')]));
    $complete($b['id'], 9);
    expect(fn () => $cancel($b['id'], 7))->toThrow(OperationsException::class, 'inversa');
});
