<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Barcode;
use App\Modules\Catalog\Domain\BarcodeInput;
use App\Modules\Catalog\Domain\CatalogException;
use App\Modules\Catalog\Domain\Factor;
use App\Modules\Catalog\Domain\NewProduct;
use App\Modules\Catalog\Domain\PresentationInput;
use App\Modules\Catalog\Domain\StockPolicy;
use App\Modules\Catalog\Domain\Symbology;

function product(array $over = []): NewProduct
{
    return new NewProduct(
        $over['name'] ?? 'Cuaderno A4 100 hojas', 1, $over['base'] ?? 1, 7, null, null, null,
        $over['presentations'] ?? [], $over['barcodes'] ?? [], $over['policies'] ?? [],
    );
}

function internal(string $code, ?int $unit = null, bool $primary = false): BarcodeInput
{
    return new BarcodeInput(Barcode::of($code, Symbology::Internal), $unit, $primary);
}

it('normaliza el nombre (espacios) y valida su largo', function () {
    expect(product(['name' => "  Cuaderno   A4 \n 100 hojas "])->name)->toBe('Cuaderno A4 100 hojas');
    expect(fn () => product(['name' => ' x ']))->toThrow(CatalogException::class, 'nombre');
});

it('siempre incluye la presentación base con factor 1, primera', function () {
    $p = product(['presentations' => [new PresentationInput(2, Factor::of(12))]]);

    expect($p->presentations)->toHaveCount(2);
    expect($p->presentations[0]->unitId)->toBe(1);
    expect($p->presentations[0]->factor->value)->toBe('1.000');
    expect($p->presentations[1]->factor->value)->toBe('12.000');
});

it('exige factor 1 para la unidad base y rechaza unidades repetidas', function () {
    expect(fn () => product(['presentations' => [new PresentationInput(1, Factor::of(2))]]))->toThrow(CatalogException::class, 'factor 1');
    expect(fn () => product(['presentations' => [new PresentationInput(2, Factor::of(12)), new PresentationInput(2, Factor::of(24))]]))
        ->toThrow(CatalogException::class, 'repetidas');
});

it('rechaza factores inválidos', function () {
    foreach (['0', '-1', 'abc', '1.2345'] as $bad) {
        expect(fn () => Factor::of($bad))->toThrow(CatalogException::class);
    }
});

it('marca como principal el primer código si ninguno lo es', function () {
    $p = product(['barcodes' => [internal('CODE-AAA'), internal('CODE-BBB')]]);

    expect($p->barcodes[0]->isPrimary)->toBeTrue();
    expect($p->barcodes[1]->isPrimary)->toBeFalse();
});

it('rechaza dos códigos principales, códigos repetidos y presentaciones inexistentes', function () {
    expect(fn () => product(['barcodes' => [internal('CODE-AAA', null, true), internal('CODE-BBB', null, true)]]))
        ->toThrow(CatalogException::class, 'principal');
    expect(fn () => product(['barcodes' => [internal('CODE-AAA'), internal('CODE-AAA')]]))
        ->toThrow(CatalogException::class, 'repetido');
    expect(fn () => product(['barcodes' => [internal('CODE-AAA', 99)]]))
        ->toThrow(CatalogException::class, 'presentación que no existe');
});

it('valida políticas de stock', function () {
    expect(fn () => new StockPolicy(1, 10, 5))->toThrow(CatalogException::class, 'máximo');
    expect(fn () => new StockPolicy(1, 10, 20, 0))->toThrow(CatalogException::class, 'reposición');
    expect(fn () => product(['policies' => [new StockPolicy(1, 5), new StockPolicy(1, 6)]]))->toThrow(CatalogException::class, 'mismo almacén');
    expect((new StockPolicy(1, 5, 20, 10))->reorderQty)->toBe('10.000');
});

it('valida el precio referencial', function () {
    expect(fn () => new NewProduct('Cuaderno', 1, 1, null, null, null, '10.555'))->toThrow(CatalogException::class, 'precio');
});
