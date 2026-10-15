<?php

declare(strict_types=1);

use App\Modules\Locations\Domain\LocationBatch;
use App\Modules\Locations\Domain\LocationCode;
use App\Modules\Locations\Domain\LocationException;

it('arma el código legible de una ubicación', function () {
    expect(LocationCode::build('bod', 1, 3, 2, 2)->value)->toBe('BOD-Z1-P03-A02-N2');
    expect((string) LocationCode::build('PIS', 12, 10, 11, 4))->toBe('PIS-Z12-P10-A11-N4');
});

it('rechaza valores fuera de rango', function () {
    expect(fn () => LocationCode::build('BODEGA', 1, 1, 1, 1))->toThrow(LocationException::class, '3 letras');
    expect(fn () => LocationCode::build('BOD', 0, 1, 1, 1))->toThrow(LocationException::class, 'zona');
    expect(fn () => LocationCode::build('BOD', 1, 100, 1, 1))->toThrow(LocationException::class, 'pasillo');
    expect(fn () => LocationCode::build('BOD', 1, 1, 1, 10))->toThrow(LocationException::class, 'nivel');
});

it('normaliza lo escaneado o digitado', function () {
    expect(LocationCode::normalize(' bod-z1-p03 -a02-n2 '))->toBe('BOD-Z1-P03-A02-N2');
});

it('el lote genera pasillos x anaqueles x niveles', function () {
    $batch = new LocationBatch(1, 1, 1, 2, 1, 3, 1, 2);
    $locs = $batch->expand('BOD');

    expect($batch->count())->toBe(12);
    expect($locs)->toHaveCount(12);
    expect($locs[0]->code)->toBe('BOD-Z1-P01-A01-N1');
    expect($locs[11]->code)->toBe('BOD-Z1-P02-A03-N2');
    expect($locs[0]->zone)->toBe('Z1');
    expect($locs[0]->aisle)->toBe('P01');
    expect(count(array_unique(array_map(static fn ($l) => $l->code, $locs))))->toBe(12);
});

it('rechaza rangos invertidos, lotes enormes y capacidades inválidas', function () {
    expect(fn () => new LocationBatch(1, 1, 5, 2, 1, 1, 1, 1))->toThrow(LocationException::class, 'pasillos');
    expect(fn () => new LocationBatch(1, 1, 1, 50, 1, 50, 1, 9))->toThrow(LocationException::class, 'Máximo');
    expect(fn () => new LocationBatch(1, 1, 1, 1, 1, 1, 1, 1, null, '0'))->toThrow(LocationException::class, 'capacidad');
});
