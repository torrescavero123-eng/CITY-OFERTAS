<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Exceptions\InventoryException;
use App\Modules\Inventory\Domain\MovementType;
use App\Modules\Inventory\Domain\NewMovement;
use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Inventory\Domain\UnitCost;
use Tests\Support\Ids;

function movement(MovementType $type, ?int $from, ?int $to, string $qty = '1', ?UnitCost $cost = null, ?int $reason = null, ?string $key = null, ?string $uuid = null): NewMovement
{
    return new NewMovement($uuid ?? Ids::uuid(), $key ?? Ids::key(), $type, 1, $from, $to, Quantity::of($qty), 1, $cost, $reason);
}

it('acepta movimientos con la forma correcta', function () {
    expect(movement(MovementType::Purchase, null, 10, '1', UnitCost::of('2')))->toBeInstanceOf(NewMovement::class);
    expect(movement(MovementType::Sale, 10, null))->toBeInstanceOf(NewMovement::class);
    expect(movement(MovementType::Transfer, 10, 11))->toBeInstanceOf(NewMovement::class);
    expect(movement(MovementType::WriteOff, 10, null, '1', null, 5))->toBeInstanceOf(NewMovement::class);
});

it('una entrada exige solo destino', function () {
    expect(fn () => movement(MovementType::Purchase, null, null, '1', UnitCost::of('1')))->toThrow(InventoryException::class);
    expect(fn () => movement(MovementType::Purchase, 10, 11, '1', UnitCost::of('1')))->toThrow(InventoryException::class);
});

it('una salida exige solo origen', function () {
    expect(fn () => movement(MovementType::Sale, null, null))->toThrow(InventoryException::class);
    expect(fn () => movement(MovementType::Sale, 10, 11))->toThrow(InventoryException::class);
});

it('una transferencia exige origen y destino distintos', function () {
    expect(fn () => movement(MovementType::Transfer, 10, null))->toThrow(InventoryException::class);
    expect(fn () => movement(MovementType::Transfer, 10, 10))->toThrow(InventoryException::class, 'distintos');
});

it('exige motivo en mermas y ajustes', function () {
    foreach ([MovementType::WriteOff, MovementType::AdjustmentOut] as $type) {
        expect(fn () => movement($type, 10, null))->toThrow(InventoryException::class, 'motivo');
    }
    expect(fn () => movement(MovementType::AdjustmentIn, null, 10))->toThrow(InventoryException::class, 'motivo');
});

it('exige costo en compras y saldo inicial, y lo prohíbe en el resto', function () {
    expect(fn () => movement(MovementType::Purchase, null, 10))->toThrow(InventoryException::class, 'costo');
    expect(fn () => movement(MovementType::OpeningBalance, null, 10))->toThrow(InventoryException::class, 'costo');
    expect(fn () => movement(MovementType::Sale, 10, null, '1', UnitCost::of('1')))->toThrow(InventoryException::class, 'costo');
});

it('rechaza cantidad cero, uuid y clave de idempotencia inválidos', function () {
    expect(fn () => movement(MovementType::Sale, 10, null, '0'))->toThrow(InventoryException::class, 'mayor que cero');
    expect(fn () => movement(MovementType::Sale, 10, null, '1', null, null, null, 'no-es-uuid'))->toThrow(InventoryException::class, 'uuid');
    expect(fn () => movement(MovementType::Sale, 10, null, '1', null, null, 'corta'))->toThrow(InventoryException::class, 'idempotencia');
});

it('cada tipo es entrada, salida o transferencia (nunca dos a la vez)', function () {
    foreach (MovementType::cases() as $type) {
        $flags = (int) $type->isEntry() + (int) $type->isExit() + (int) $type->isTransfer();
        expect($flags)->toBe(1);
    }
});

it('cada tipo declara el permiso requerido', function () {
    foreach (MovementType::cases() as $type) {
        expect($type->requiredPermission())->not->toBe('');
    }
});
