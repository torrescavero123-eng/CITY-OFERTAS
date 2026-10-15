<?php

declare(strict_types=1);

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Inventory\Domain\UnitCost;
use App\Modules\Inventory\Domain\WeightedAverageCost;

it('calcula el promedio ponderado con redondeo a 4 decimales', function () {
    // (100 x 2.50 + 50 x 3.00) / 150 = 2.666666...
    $next = WeightedAverageCost::next(Quantity::of('100'), UnitCost::of('2.5'), Quantity::of('50'), UnitCost::of('3'));

    expect($next->value)->toBe('2.6667');
});

it('con stock cero el promedio es el costo de la compra', function () {
    $next = WeightedAverageCost::next(Quantity::zero(), UnitCost::zero(), Quantity::of('10'), UnitCost::of('2.5'));

    expect($next->value)->toBe('2.5000');
});

it('redondea half-up en el límite', function () {
    // (1 x 0.0001 + 1 x 0.0002) / 2 = 0.00015 -> 0.0002
    $next = WeightedAverageCost::next(Quantity::of('1'), UnitCost::of('0.0001'), Quantity::of('1'), UnitCost::of('0.0002'));

    expect($next->value)->toBe('0.0002');
});

it('mantiene el promedio si el costo entrante es igual', function () {
    $next = WeightedAverageCost::next(Quantity::of('37'), UnitCost::of('4.1234'), Quantity::of('13'), UnitCost::of('4.1234'));

    expect($next->value)->toBe('4.1234');
});
