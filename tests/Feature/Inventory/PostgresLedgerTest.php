<?php

declare(strict_types=1);

use App\Modules\Inventory\Application\RegisterMovement;
use App\Modules\Inventory\Domain\Exceptions\InventoryException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\Support\MovementFactory as M;

it('registra una compra, actualiza saldo y costo promedio en la BD real', function () {
    $user = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location();
    $register = app(RegisterMovement::class);

    $register(M::purchase($product, $loc, 100, '2.5', $user));
    $register(M::purchase($product, $loc, 50, '3', $user));

    expect(Fixtures::balance($product, $loc))->toBe('150.000');
    expect((string) DB::table('products')->where('id', $product)->value('cost_avg'))->toBe('2.6667');
    expect((string) DB::table('products')->where('id', $product)->value('last_cost'))->toBe('3.0000');
});

it('la transferencia es atómica y el ledger cuadra con los saldos', function () {
    $user = Fixtures::user();
    $product = Fixtures::product();
    $bodega = Fixtures::location('BOD');
    $piso = Fixtures::location('PIS');
    $register = app(RegisterMovement::class);

    $register(M::purchase($product, $bodega, 20, '1', $user));
    $register(M::transfer($product, $bodega, $piso, 5, $user));

    expect(Fixtures::balance($product, $bodega))->toBe('15.000');
    expect(Fixtures::balance($product, $piso))->toBe('5.000');
    expect(DB::select('SELECT * FROM fn_reconcile_ledger()'))->toHaveCount(0);
});

it('rechaza stock insuficiente sin dejar rastro en el ledger', function () {
    $user = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location();
    $register = app(RegisterMovement::class);
    $register(M::purchase($product, $loc, 3, '1', $user));
    $before = DB::table('stock_movements')->count();

    expect(fn () => $register(M::sale($product, $loc, 5, $user)))->toThrow(InventoryException::class, 'Stock insuficiente');

    expect(DB::table('stock_movements')->count())->toBe($before);
    expect(Fixtures::balance($product, $loc))->toBe('3.000');
});

it('es idempotente contra la BD real', function () {
    $user = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location();
    $register = app(RegisterMovement::class);
    $movement = M::purchase($product, $loc, 10, '2', $user);

    $first = $register($movement);
    $again = $register($movement);

    expect($again->duplicate)->toBeTrue();
    expect($again->movementId)->toBe($first->movementId);
    expect(DB::table('stock_movements')->where('product_id', $product)->count())->toBe(1);
    expect(Fixtures::balance($product, $loc))->toBe('10.000');
});

it('rechaza ubicaciones congeladas salvo ajustes de conteo', function () {
    $user = Fixtures::user();
    $product = Fixtures::product();
    $frozen = Fixtures::location('BOD', ['is_frozen' => true]);
    $register = app(RegisterMovement::class);

    expect(fn () => $register(M::purchase($product, $frozen, 1, '1', $user)))->toThrow(InventoryException::class, 'congelada');

    $register(M::purchase($product, $frozen, 4, '1', $user, sourceType: 'COUNT'));
    expect(Fixtures::balance($product, $frozen))->toBe('4.000');
});

it('el historial es inmutable: UPDATE y DELETE son rechazados por la BD', function () {
    $user = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location();
    app(RegisterMovement::class)(M::purchase($product, $loc, 1, '1', $user));

    // DB::transaction anidada = savepoint, para no abortar la transacción del test.
    expect(fn () => DB::transaction(fn () => DB::table('stock_movements')->where('product_id', $product)->update(['quantity' => 99])))
        ->toThrow(QueryException::class, 'inmutable');
    expect(fn () => DB::transaction(fn () => DB::table('stock_movements')->where('product_id', $product)->delete()))
        ->toThrow(QueryException::class, 'inmutable');
});

it('la BD rechaza stock negativo aunque el código fallara', function () {
    $product = Fixtures::product();
    $loc = Fixtures::location();

    expect(fn () => DB::transaction(fn () => DB::table('stock_balances')->insert([
        'product_id' => $product, 'location_id' => $loc, 'quantity' => '-1',
    ])))->toThrow(QueryException::class);
});

it('la BD rechaza movimientos mal formados y claves de idempotencia repetidas', function () {
    $user = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location();
    $base = [
        'uuid' => (string) Str::uuid(), 'type' => 'ENTRADA_COMPRA', 'product_id' => $product,
        'to_location_id' => $loc, 'quantity' => 1, 'unit_cost' => 1, 'user_id' => $user, 'idempotency_key' => 'dup-'.$product,
    ];
    DB::table('stock_movements')->insert($base);

    // entrada sin costo
    expect(fn () => DB::transaction(fn () => DB::table('stock_movements')->insert(
        ['uuid' => (string) Str::uuid(), 'idempotency_key' => 'x-'.$product, 'unit_cost' => null] + $base,
    )))->toThrow(QueryException::class);

    // misma clave de idempotencia
    expect(fn () => DB::transaction(fn () => DB::table('stock_movements')->insert(
        ['uuid' => (string) Str::uuid()] + $base,
    )))->toThrow(QueryException::class);
});
