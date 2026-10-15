<?php

declare(strict_types=1);

use App\Modules\Inventory\Application\RegisterMovement;
use App\Modules\Inventory\Domain\Exceptions\InventoryException;
use App\Modules\Locations\Application\ChangeLocationState;
use App\Modules\Locations\Application\GenerateLocations;
use App\Modules\Locations\Domain\LocationBatch;
use App\Modules\Locations\Domain\LocationException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\Support\MovementFactory as M;

function bodegaId(): int
{
    return (int) DB::table('warehouses')->where('code', 'BOD')->value('id');
}

it('genera ubicaciones en lote con zona, pasillo, anaquel y nivel', function () {
    $result = app(GenerateLocations::class)(new LocationBatch(bodegaId(), 1, 1, 2, 1, 3, 1, 2));

    expect($result)->toBe(['created' => 12, 'skipped' => 0]);
    $first = DB::table('locations')->where('code', 'BOD-Z1-P01-A01-N1')->first();
    expect($first->zone)->toBe('Z1');
    expect($first->aisle)->toBe('P01');
    expect($first->rack)->toBe('A01');
    expect((int) $first->level)->toBe(1);
    expect(DB::table('locations')->where('code', 'like', 'BOD-Z1-%')->count())->toBe(12);
    expect(DB::table('audit_logs')->where('action', 'location.generated')->count())->toBe(1);
});

it('repetir el lote no duplica ubicaciones', function () {
    $generate = app(GenerateLocations::class);
    $generate(new LocationBatch(bodegaId(), 2, 1, 1, 1, 2, 1, 1));

    $again = $generate(new LocationBatch(bodegaId(), 2, 1, 2, 1, 2, 1, 1));

    expect($again)->toBe(['created' => 2, 'skipped' => 2]);
    expect(DB::table('locations')->where('code', 'like', 'BOD-Z2-%')->count())->toBe(4);
});

it('una ubicación congelada rechaza movimientos hasta que se descongela', function () {
    $actor = Fixtures::user();
    $product = Fixtures::product();
    app(GenerateLocations::class)(new LocationBatch(bodegaId(), 3, 1, 1, 1, 1, 1, 1));
    $loc = (int) DB::table('locations')->where('code', 'BOD-Z3-P01-A01-N1')->value('id');
    $state = app(ChangeLocationState::class);
    $register = app(RegisterMovement::class);

    $state->freeze($loc, $actor);
    expect(fn () => $register(M::purchase($product, $loc, 5, '1', $actor)))->toThrow(InventoryException::class, 'congelada');

    $state->unfreeze($loc, $actor);
    $register(M::purchase($product, $loc, 5, '1', $actor));
    expect(Fixtures::balance($product, $loc))->toBe('5.000');
});

it('no se desactiva una ubicación con stock ni una de recepción', function () {
    $actor = Fixtures::user();
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    app(RegisterMovement::class)(M::purchase($product, $loc, 3, '1', $actor));
    $state = app(ChangeLocationState::class);
    $staging = (int) DB::table('locations')->where('code', 'BOD-REC')->value('id');

    expect(fn () => $state->deactivate($loc, $actor))->toThrow(LocationException::class, 'aún tiene');
    expect(fn () => $state->deactivate($staging, $actor))->toThrow(LocationException::class, 'de paso');

    app(RegisterMovement::class)(M::sale($product, $loc, 3, $actor));
    $state->deactivate($loc, $actor);
    expect((bool) DB::table('locations')->where('id', $loc)->value('is_active'))->toBeFalse();
});
