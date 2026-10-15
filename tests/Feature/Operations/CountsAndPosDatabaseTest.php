<?php

declare(strict_types=1);

use App\Modules\Inventory\Application\RegisterMovement;
use App\Modules\Inventory\Domain\Exceptions\InventoryException;
use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Application\CancelCount;
use App\Modules\Operations\Application\CloseCount;
use App\Modules\Operations\Application\ImportPosSales;
use App\Modules\Operations\Application\RecordCount;
use App\Modules\Operations\Application\StartCount;
use App\Modules\Operations\Application\SubmitCountForReview;
use App\Modules\Operations\Domain\CountScopeType;
use App\Modules\Operations\Domain\NewCount;
use App\Modules\Operations\Domain\OperationsException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\Support\MovementFactory as M;

function countBodega(): int
{
    return (int) DB::table('warehouses')->where('code', 'BOD')->value('id');
}

function zoneLocation(string $zone, string $warehouse = 'BOD'): int
{
    return Fixtures::location($warehouse, ['zone' => $zone]);
}

it('conteo completo: congela, cuenta, recuenta, cierra con ajustes y deja el ledger cuadrado', function () {
    $counter = Fixtures::user();
    $supervisor = Fixtures::user();
    $p1 = Fixtures::product();
    $p2 = Fixtures::product();
    $a = zoneLocation('ZT1');
    $b = zoneLocation('ZT1');
    $empty = zoneLocation('ZT1');
    $register = app(RegisterMovement::class);
    $register(M::purchase($p1, $a, 10, '2', $supervisor));
    $register(M::purchase($p2, $b, 4, '5', $supervisor));

    $started = app(StartCount::class)(new NewCount(countBodega(), CountScopeType::Zone, 'ZT1', $supervisor));
    expect($started['locations'])->toBe(3);
    expect($started['lines'])->toBe(2);
    expect((bool) DB::table('locations')->where('id', $a)->value('is_frozen'))->toBeTrue();
    expect(fn () => $register(M::purchase($p1, $a, 1, '2', $supervisor)))->toThrow(InventoryException::class, 'congelada');

    $record = app(RecordCount::class);
    $record($started['id'], $p1, $a, Quantity::of('10'), $counter);          // correcto
    $record($started['id'], $p2, $b, Quantity::of('3'), $counter);           // falta 1
    $record($started['id'], $p1, $empty, Quantity::of('2'), $counter);       // hallazgo: 2 que nadie tenía registrados

    $review = app(SubmitCountForReview::class)($started['id'], $counter);
    expect($review)->toBe(['lines' => 3, 'withDifference' => 2]);

    $record($started['id'], $p2, $b, Quantity::of('3'), $counter);           // el reconteo confirma
    $record($started['id'], $p1, $empty, Quantity::of('2'), $counter);
    expect(fn () => app(CloseCount::class)($started['id'], $counter))->toThrow(OperationsException::class, 'otra persona');

    $result = app(CloseCount::class)($started['id'], $supervisor);

    expect($result)->toBe(['adjustments' => 2, 'lines' => 3, 'accuracyPct' => '33.33']);
    expect(Fixtures::balance($p2, $b))->toBe('3.000');
    expect(Fixtures::balance($p1, $empty))->toBe('2.000');
    expect(Fixtures::balance($p1, $a))->toBe('10.000');
    expect((bool) DB::table('locations')->where('id', $a)->value('is_frozen'))->toBeFalse();
    expect(DB::table('stock_counts')->where('id', $started['id'])->value('status'))->toBe('CLOSED');
    expect(DB::table('stock_count_lines')->where('count_id', $started['id'])->whereNotNull('adjustment_request_id')->count())->toBe(2);
    expect(DB::table('stock_movements')->where('source_type', 'COUNT')->where('source_id', $started['id'])->count())->toBe(2);
    expect((int) DB::table('stock_movements')->where('source_type', 'COUNT')->first()->approved_by)->toBe($supervisor);
    expect(DB::select('SELECT * FROM fn_reconcile_ledger()'))->toHaveCount(0);

    // ya sin congelamiento, la operación normal se reanuda
    $register(M::purchase($p1, $a, 1, '2', $supervisor));
    expect(Fixtures::balance($p1, $a))->toBe('11.000');
});

it('anular un conteo en curso descongela y no deja ajustes', function () {
    $actor = Fixtures::user();
    $product = Fixtures::product();
    $loc = zoneLocation('ZT2');
    app(RegisterMovement::class)(M::purchase($product, $loc, 5, '1', $actor));
    $count = app(StartCount::class)(new NewCount(countBodega(), CountScopeType::Zone, 'ZT2', $actor));
    expect((bool) DB::table('locations')->where('id', $loc)->value('is_frozen'))->toBeTrue();

    app(CancelCount::class)($count['id'], $actor);

    expect((bool) DB::table('locations')->where('id', $loc)->value('is_frozen'))->toBeFalse();
    expect(DB::table('stock_counts')->where('id', $count['id'])->value('status'))->toBe('CANCELLED');
    expect(DB::table('stock_movements')->where('source_type', 'COUNT')->count())->toBe(0);
});

it('dos conteos no pueden congelar las mismas ubicaciones', function () {
    $actor = Fixtures::user();
    $loc = zoneLocation('ZT3');
    app(RegisterMovement::class)(M::purchase(Fixtures::product(), $loc, 1, '1', $actor));
    app(StartCount::class)(new NewCount(countBodega(), CountScopeType::Zone, 'ZT3', $actor));

    expect(fn () => app(StartCount::class)(new NewCount(countBodega(), CountScopeType::Location, DB::table('locations')->where('id', $loc)->value('code'), $actor)))
        ->toThrow(OperationsException::class, 'congelada');
});

it('el conteo de clase A incluye solo los productos que explican el 80 % de lo vendido', function () {
    $actor = Fixtures::user();
    $star = Fixtures::product();
    $minor = Fixtures::product();
    $loc = zoneLocation('ZT4');
    $register = app(RegisterMovement::class);
    $register(M::purchase($star, $loc, 200, '10', $actor));
    $register(M::sale($star, $loc, 100, $actor));      // vendió 100 x 10 = S/ 1000
    $register(M::purchase($minor, $loc, 10, '1', $actor));
    $register(M::sale($minor, $loc, 1, $actor));       // vendió 1 x 1 = S/ 1
    DB::statement('REFRESH MATERIALIZED VIEW mv_product_rotation_90d');

    $count = app(StartCount::class)(new NewCount(countBodega(), CountScopeType::AbcA, null, $actor));

    expect($count['lines'])->toBe(1);
    expect(DB::table('stock_count_lines')->where('count_id', $count['id'])->value('product_id'))->toBe($star);
});

it('por categoría incluye los productos de sus subcategorías', function () {
    $actor = Fixtures::user();
    $root = (int) DB::table('categories')->whereNull('parent_id')->where('code', 'LIB')->value('id');
    $child = (int) DB::table('categories')->insertGetId(['parent_id' => $root, 'code' => '', 'name' => 'Cuadernos', 'depth' => 2], 'id');
    $inChild = Fixtures::product();
    $other = Fixtures::product();
    DB::table('products')->where('id', $inChild)->update(['category_id' => $child]);
    DB::table('products')->where('id', $other)->update(['category_id' => (int) DB::table('categories')->whereNull('parent_id')->where('code', 'JUG')->value('id')]);
    $loc = zoneLocation('ZT5');
    $register = app(RegisterMovement::class);
    $register(M::purchase($inChild, $loc, 3, '1', $actor));
    $register(M::purchase($other, $loc, 3, '1', $actor));

    $count = app(StartCount::class)(new NewCount(countBodega(), CountScopeType::Category, (string) $root, $actor));

    expect($count['lines'])->toBe(1);
    expect(DB::table('stock_count_lines')->where('count_id', $count['id'])->value('product_id'))->toBe($inChild);
});

// ----------------------------- Importación de ventas del POS -----------------------------

function posProduct(int $stockAtFloor, string $cost = '3'): array
{
    $actor = Fixtures::user();
    $product = Fixtures::product();
    $floor = Fixtures::location('PIS');
    app(RegisterMovement::class)(M::purchase($product, $floor, $stockAtFloor, $cost, $actor));
    DB::table('product_locations')->insert(['product_id' => $product, 'location_id' => $floor, 'is_primary' => true]);
    DB::table('product_barcodes')->insert(['product_id' => $product, 'barcode' => 'POS'.random_int(100000, 999999), 'symbology' => 'INTERNAL', 'is_primary' => true]);

    return [$actor, $product, $floor, (string) DB::table('products')->where('id', $product)->value('sku'), (string) DB::table('product_barcodes')->where('product_id', $product)->value('barcode')];
}

it('la importación descuenta del piso, acepta SKU o código de barras y registra el archivo', function () {
    [$actor, $product, $floor, $sku, $barcode] = posProduct(10);

    $result = app(ImportPosSales::class)("sku,cantidad\n{$sku},2\n{$barcode},3\n", 'ventas-2026-10-09.csv', now('America/Lima')->format('Y-m-d'), $actor, true);

    expect($result->okRows)->toBe(1);
    expect($result->units)->toBe('5.000');
    expect(Fixtures::balance($product, $floor))->toBe('5.000');
    $movement = DB::table('stock_movements')->where('product_id', $product)->where('type', 'SALIDA_VENTA')->first();
    expect($movement->source_type)->toBe('POS_IMPORT');
    expect((string) $movement->unit_cost)->toBe('3.0000'); // valorizada al costo promedio
    $job = DB::table('import_jobs')->where('id', $result->importId)->first();
    expect($job->kind)->toBe('POS_SALES');
    expect($job->mode)->toBe('COMMIT');
    expect((int) $job->ok_rows)->toBe(1);
    expect(DB::select('SELECT * FROM fn_reconcile_ledger()'))->toHaveCount(0);
});

it('el mismo archivo no se confirma dos veces y la simulación no toca el stock', function () {
    [$actor, $product, $floor, $sku] = posProduct(10);
    $csv = "sku,cantidad\n{$sku},4\n";
    $import = app(ImportPosSales::class);
    $today = now('America/Lima')->format('Y-m-d');

    $dry = $import($csv, 'v.csv', $today, $actor, false);
    expect($dry->committed)->toBeFalse();
    expect(Fixtures::balance($product, $floor))->toBe('10.000');

    $import($csv, 'v.csv', $today, $actor, true);
    expect(fn () => $import($csv, 'copia.csv', $today, $actor, true))->toThrow(OperationsException::class, 'ya se importó');
    expect(Fixtures::balance($product, $floor))->toBe('6.000');
});

it('las filas con error van al reporte y las válidas se aplican', function () {
    [$actor, $product, $floor, $sku] = posProduct(2);

    $result = app(ImportPosSales::class)("sku,cantidad\nNO-EXISTE,1\n{$sku},5\n", 'v.csv', now('America/Lima')->format('Y-m-d'), $actor, true);

    expect($result->okRows)->toBe(0);
    expect($result->errors)->toHaveCount(2);
    expect(Fixtures::balance($product, $floor))->toBe('2.000'); // stock insuficiente: no se descuenta
    expect($result->errorReportCsv())->toContain('No se encontró');
});
