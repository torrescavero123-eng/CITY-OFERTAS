<?php

declare(strict_types=1);

use App\Filament\Resources\Products\ProductFormMapper;
use App\Modules\Catalog\Domain\CatalogException;

function formData(array $over = []): array
{
    return $over + [
        'name' => '  Cuaderno   A4 ',
        'category_id' => '1',
        'brand_id' => '',
        'base_unit_id' => '1',
        'reference_price' => '5.5',
        'description' => '',
        'presentations' => ['a1b2' => ['unit_id' => '2', 'factor' => '12', 'is_purchase_default' => true]],
        'barcodes' => [
            'k1' => ['symbology' => 'EAN13', 'barcode' => '4006381333931', 'presentation_unit_id' => '', 'is_primary' => false],
            'k2' => ['symbology' => 'INTERNAL', 'barcode' => 'DOCENA-1', 'presentation_unit_id' => '2', 'is_primary' => false],
        ],
        'policies' => ['p1' => ['warehouse_id' => '1', 'min_qty' => '10', 'max_qty' => '100', 'reorder_qty' => '50']],
    ];
}

it('convierte el formulario en un producto del dominio', function () {
    $p = ProductFormMapper::toNewProduct(formData(), 7);

    expect($p->name)->toBe('Cuaderno A4');
    expect($p->actorId)->toBe(7);
    expect($p->brandId)->toBeNull();
    expect($p->description)->toBeNull();
    expect($p->referencePrice)->toBe('5.50');
    expect($p->presentations)->toHaveCount(2); // base + docena
    expect($p->presentations[1]->factor->value)->toBe('12.000');
    expect($p->barcodes)->toHaveCount(2);
    expect($p->barcodes[0]->isPrimary)->toBeTrue(); // el primero queda como principal
    expect($p->barcodes[1]->presentationUnitId)->toBe(2);
    expect($p->policies[0]->reorderQty)->toBe('50.000');
});

it('acepta un formulario mínimo (sin presentaciones, códigos ni políticas)', function () {
    $p = ProductFormMapper::toNewProduct(['name' => 'Lápiz', 'category_id' => 1, 'base_unit_id' => 1], null);

    expect($p->presentations)->toHaveCount(1);
    expect($p->barcodes)->toBe([]);
    expect($p->policies)->toBe([]);
    expect($p->referencePrice)->toBeNull();
});

it('el mínimo vacío de una política se toma como cero', function () {
    $p = ProductFormMapper::toNewProduct(formData(['policies' => [['warehouse_id' => 1, 'min_qty' => '']]]), null);

    expect($p->policies[0]->minQty)->toBe('0.000');
});

it('rechaza datos incompletos o inválidos con mensajes claros', function () {
    expect(fn () => ProductFormMapper::toNewProduct(formData(['category_id' => '']), null))->toThrow(CatalogException::class, 'la categoría');
    expect(fn () => ProductFormMapper::toNewProduct(formData(['base_unit_id' => null]), null))->toThrow(CatalogException::class, 'la unidad base');
    expect(fn () => ProductFormMapper::toNewProduct(formData(['reference_price' => 'abc']), null))->toThrow(CatalogException::class, 'precio');
    expect(fn () => ProductFormMapper::toNewProduct(formData(['barcodes' => [['symbology' => '', 'barcode' => 'X']]]), null))->toThrow(CatalogException::class, 'tipo de código');
    expect(fn () => ProductFormMapper::toNewProduct(formData(['barcodes' => [['symbology' => 'EAN13', 'barcode' => '4006381333932']]]), null))->toThrow(CatalogException::class, 'verificador');
    expect(fn () => ProductFormMapper::toNewProduct(formData(['presentations' => [['unit_id' => 2, 'factor' => '0']]]), null))->toThrow(CatalogException::class, 'Factor');
});

it('arma los cambios de edición exigiendo la versión', function () {
    $c = ProductFormMapper::toChanges(['version' => 3, 'name' => 'Nuevo nombre', 'category_id' => '2', 'brand_id' => '', 'reference_price' => '7'], 9);

    expect($c->expectedVersion)->toBe(3);
    expect($c->name)->toBe('Nuevo nombre');
    expect($c->categoryId)->toBe(2);
    expect($c->brandId)->toBeNull();
    expect($c->referencePrice)->toBe('7.00');
    expect(fn () => ProductFormMapper::toChanges(['name' => 'X'], 9))->toThrow(CatalogException::class, 'versión');
});
