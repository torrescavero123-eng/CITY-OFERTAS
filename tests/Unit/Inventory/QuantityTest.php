<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Exceptions\InventoryException;
use App\Modules\Inventory\Domain\Quantity;

it('normaliza a 3 decimales', function () {
    expect(Quantity::of('5')->value)->toBe('5.000');
    expect(Quantity::of(7)->value)->toBe('7.000');
    expect(Quantity::of('0.5')->value)->toBe('0.500');
});

it('rechaza valores inválidos', function () {
    foreach (['-1', '1.2345', 'abc', '', ' ', '1,5', '1e3', '99999999999999'] as $bad) {
        expect(fn () => Quantity::of($bad))->toThrow(InventoryException::class);
    }
});

it('suma con precisión decimal (sin errores de float)', function () {
    expect(Quantity::of('0.1')->add(Quantity::of('0.2'))->value)->toBe('0.300');
});

it('resta y no permite resultados negativos', function () {
    expect(Quantity::of('5')->subtract(Quantity::of('2.5'))->value)->toBe('2.500');
    expect(fn () => Quantity::of('1')->subtract(Quantity::of('2')))->toThrow(InventoryException::class);
});

it('compara cantidades', function () {
    expect(Quantity::of('1')->lessThan(Quantity::of('2')))->toBeTrue();
    expect(Quantity::of('2')->lessThan(Quantity::of('2')))->toBeFalse();
    expect(Quantity::of('2')->greaterThanOrEqual(Quantity::of('2.000')))->toBeTrue();
    expect(Quantity::of('2.0')->equals(Quantity::of('2.000')))->toBeTrue();
    expect(Quantity::zero()->isPositive())->toBeFalse();
    expect(Quantity::of('0.001')->isPositive())->toBeTrue();
});
