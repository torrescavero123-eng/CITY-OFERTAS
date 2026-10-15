<?php

declare(strict_types=1);

use App\Modules\Locations\Application\ChangeLocationState;
use App\Modules\Locations\Application\GenerateLocations;
use App\Modules\Locations\Domain\LocationBatch;
use App\Modules\Locations\Domain\LocationException;
use App\Modules\Locations\Domain\WarehouseInfo;
use Tests\Support\Locations\InMemoryLocationRepository;
use Tests\Support\PassthroughTransaction;
use Tests\Support\RecordingAuditTrail;

function locationKit(): array
{
    $repo = new InMemoryLocationRepository;
    $repo->warehouses = [1 => new WarehouseInfo(1, 'BOD', true), 2 => new WarehouseInfo(2, 'PIS', false)];
    $audit = new RecordingAuditTrail;
    $tx = new PassthroughTransaction;

    return [$repo, $audit, new GenerateLocations($repo, $tx, $audit), new ChangeLocationState($repo, $tx, $audit)];
}

it('genera las ubicaciones del lote y audita', function () {
    [$repo, $audit, $generate] = locationKit();

    $result = $generate(new LocationBatch(1, 1, 1, 2, 1, 2, 1, 2, 7));

    expect($result)->toBe(['created' => 8, 'skipped' => 0]);
    expect($repo->rows)->toHaveCount(8);
    expect($audit->entries[0]->action)->toBe('location.generated');
    expect($audit->entries[0]->newValues['created'])->toBe(8);
    expect($audit->entries[0]->actorId)->toBe(7);
});

it('repetir el lote no duplica: crea solo lo que falta', function () {
    [$repo, , $generate] = locationKit();
    $generate(new LocationBatch(1, 1, 1, 1, 1, 2, 1, 1));

    $result = $generate(new LocationBatch(1, 1, 1, 2, 1, 2, 1, 1));

    expect($result)->toBe(['created' => 2, 'skipped' => 2]);
    expect($repo->rows)->toHaveCount(4);
});

it('no genera en almacenes inexistentes o desactivados', function () {
    [, , $generate] = locationKit();

    expect(fn () => $generate(new LocationBatch(9, 1, 1, 1, 1, 1, 1, 1)))->toThrow(LocationException::class, 'no existe');
    expect(fn () => $generate(new LocationBatch(2, 1, 1, 1, 1, 1, 1, 1)))->toThrow(LocationException::class, 'desactivado');
});

it('congela y descongela de forma idempotente, auditando una vez por cambio', function () {
    [$repo, $audit, $generate, $state] = locationKit();
    $generate(new LocationBatch(1, 1, 1, 1, 1, 1, 1, 1));

    $state->freeze(1, 7);
    $state->freeze(1, 7);
    expect($repo->rows[1]['record']->isFrozen)->toBeTrue();

    $state->unfreeze(1, 7);
    $state->unfreeze(1, 7);
    expect($repo->rows[1]['record']->isFrozen)->toBeFalse();

    $actions = array_map(static fn ($e) => $e->action, $audit->entries);
    expect(count(array_filter($actions, static fn ($a) => $a === 'location.frozen')))->toBe(1);
    expect(count(array_filter($actions, static fn ($a) => $a === 'location.unfrozen')))->toBe(1);
});

it('no desactiva ubicaciones con stock, de paso o congeladas', function () {
    [$repo, , $generate, $state] = locationKit();
    $generate(new LocationBatch(1, 1, 1, 1, 1, 3, 1, 1));
    $repo->stock[1] = '4.000';
    $repo->markStaging(2);
    $state->freeze(3);

    expect(fn () => $state->deactivate(1))->toThrow(LocationException::class, 'aún tiene 4.000');
    expect(fn () => $state->deactivate(2))->toThrow(LocationException::class, 'de paso');
    expect(fn () => $state->deactivate(3))->toThrow(LocationException::class, 'congelada');
    expect($repo->rows[1]['record']->isActive)->toBeTrue();
});

it('desactiva y reactiva una ubicación vacía', function () {
    [$repo, , $generate, $state] = locationKit();
    $generate(new LocationBatch(1, 1, 1, 1, 1, 1, 1, 1));

    $state->deactivate(1);
    expect($repo->rows[1]['record']->isActive)->toBeFalse();
    $state->activate(1);
    expect($repo->rows[1]['record']->isActive)->toBeTrue();
    expect(fn () => $state->freeze(404))->toThrow(LocationException::class, 'no existe');
});

it('congela y descongela en masa con una sola auditoría', function () {
    [$repo, $audit, $generate, $state] = locationKit();
    $generate(new LocationBatch(1, 1, 1, 1, 1, 3, 1, 1));
    $before = count($audit->entries);

    expect($state->freezeMany([1, 2, 3], 7, 'CNT-2026-000001'))->toBe(3);
    expect($repo->rows[2]['record']->isFrozen)->toBeTrue();
    expect(count($audit->entries))->toBe($before + 1);

    expect($state->unfreezeMany([1, 2, 3], 7, 'CNT-2026-000001'))->toBe(3);
    expect($repo->rows[2]['record']->isFrozen)->toBeFalse();
    expect($state->unfreezeMany([1, 2, 3], 7))->toBe(0); // idempotente
});

it('el congelamiento masivo es todo o nada', function () {
    [$repo, , $generate, $state] = locationKit();
    $generate(new LocationBatch(1, 1, 1, 1, 1, 3, 1, 1));
    $state->freeze(2);

    expect(fn () => $state->freezeMany([1, 2, 3]))->toThrow(LocationException::class, 'congelada');
    expect($repo->rows[1]['record']->isFrozen)->toBeFalse();
    expect($repo->rows[3]['record']->isFrozen)->toBeFalse();
    expect(fn () => $state->freezeMany([1, 99]))->toThrow(LocationException::class, 'no existe');
});
