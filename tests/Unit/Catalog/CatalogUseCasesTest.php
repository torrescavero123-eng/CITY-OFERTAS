<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Application\DeactivateProduct;
use App\Modules\Catalog\Application\FindSimilarProducts;
use App\Modules\Catalog\Application\UpdateProduct;
use App\Modules\Catalog\Domain\Barcode;
use App\Modules\Catalog\Domain\BarcodeInput;
use App\Modules\Catalog\Domain\CatalogException;
use App\Modules\Catalog\Domain\CategoryInfo;
use App\Modules\Catalog\Domain\Factor;
use App\Modules\Catalog\Domain\NewProduct;
use App\Modules\Catalog\Domain\PresentationInput;
use App\Modules\Catalog\Domain\ProductChanges;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Domain\StockPolicy;
use App\Modules\Catalog\Domain\Symbology;
use Tests\Support\Catalog\InMemoryCatalogRepository;
use Tests\Support\Catalog\InMemorySkuSequence;
use Tests\Support\PassthroughTransaction;
use Tests\Support\RecordingAuditTrail;

function catalogKit(): array
{
    $repo = new InMemoryCatalogRepository;
    $repo->categories = [1 => new CategoryInfo(1, 'LIB', true), 2 => new CategoryInfo(2, 'JUG', true), 3 => new CategoryInfo(3, 'OLD', false)];
    $repo->brands = [1];
    $repo->units = [1, 2];
    $repo->warehouses = [1, 2];
    $audit = new RecordingAuditTrail;
    $tx = new PassthroughTransaction;

    return [$repo, $audit, new CreateProduct($repo, new InMemorySkuSequence, $tx, $audit), new UpdateProduct($repo, $tx, $audit), new DeactivateProduct($repo, $tx, $audit)];
}

function draft(string $name = 'Cuaderno A4 100 hojas', int $category = 1, array $extra = []): NewProduct
{
    return new NewProduct(
        $name, $category, 1, 7, null, $extra['brand'] ?? null, null,
        $extra['presentations'] ?? [], $extra['barcodes'] ?? [], $extra['policies'] ?? [],
    );
}

it('crea el producto con SKU correlativo por categoría raíz y audita', function () {
    [$repo, $audit, $create] = catalogKit();

    $a = $create(draft('Cuaderno A4'));
    $b = $create(draft('Lápiz HB'));
    $c = $create(draft('Pelota', 2));

    expect($a['sku'])->toBe('LIB-000001');
    expect($b['sku'])->toBe('LIB-000002');
    expect($c['sku'])->toBe('JUG-000001');
    expect($audit->entries)->toHaveCount(3);
    expect($audit->entries[0]->action)->toBe('product.created');
    expect($audit->entries[0]->actorId)->toBe(7);
});

it('rechaza categoría inexistente o desactivada, marca, unidad y almacén inexistentes', function () {
    [, , $create] = catalogKit();

    expect(fn () => $create(draft('Cuaderno', 99)))->toThrow(CatalogException::class, 'categoría');
    expect(fn () => $create(draft('Cuaderno', 3)))->toThrow(CatalogException::class, 'desactivada');
    expect(fn () => $create(draft('Cuaderno', 1, ['brand' => 50])))->toThrow(CatalogException::class, 'marca');
    expect(fn () => $create(draft('Cuaderno', 1, ['presentations' => [new PresentationInput(77, Factor::of(12))]])))->toThrow(CatalogException::class, 'unidad');
    expect(fn () => $create(draft('Cuaderno', 1, ['policies' => [new StockPolicy(88, 5)]])))->toThrow(CatalogException::class, 'almacén');
});

it('rechaza un código de barras que ya pertenece a otro producto', function () {
    [$repo, , $create] = catalogKit();
    $code = new BarcodeInput(Barcode::of('4006381333931', Symbology::Ean13));
    $create(draft('Primero', 1, ['barcodes' => [$code]]));

    expect(fn () => $create(draft('Segundo', 1, ['barcodes' => [$code]])))->toThrow(CatalogException::class, 'ya pertenece');
    expect(count($repo->products))->toBe(1);
});

it('actualiza con control de versión y audita solo los campos que cambiaron', function () {
    [$repo, $audit, $create, $update] = catalogKit();
    $id = $create(draft('Cuaderno A4'))['id'];

    $update($id, new ProductChanges(1, 7, 'Cuaderno A4 rayado', null, null, null, '5.50'));

    expect($repo->products[$id]->name)->toBe('Cuaderno A4 rayado');
    expect($repo->products[$id]->version)->toBe(2);
    $entry = $audit->entries[1];
    expect($entry->action)->toBe('product.updated');
    expect($entry->oldValues)->toBe(['name' => 'Cuaderno A4', 'reference_price' => null]);
    expect($entry->newValues)->toBe(['name' => 'Cuaderno A4 rayado', 'reference_price' => '5.50']);
});

it('rechaza editar con una versión vieja (otra persona ya lo cambió)', function () {
    [$repo, , $create, $update] = catalogKit();
    $id = $create(draft('Cuaderno A4'))['id'];
    $update($id, new ProductChanges(1, 7, 'Nombre nuevo'));

    expect(fn () => $update($id, new ProductChanges(1, 7, 'Otro nombre')))->toThrow(CatalogException::class, 'modificado por otra persona');
    expect($repo->products[$id]->name)->toBe('Nombre nuevo');
});

it('no incrementa la versión si no hay cambios reales', function () {
    [$repo, $audit, $create, $update] = catalogKit();
    $id = $create(draft('Cuaderno A4'))['id'];

    $update($id, new ProductChanges(1, 7, 'Cuaderno A4'));

    expect($repo->products[$id]->version)->toBe(1);
    expect($audit->entries)->toHaveCount(1);
});

it('valida categoría y marca al actualizar', function () {
    [, , $create, $update] = catalogKit();
    $id = $create(draft())['id'];

    expect(fn () => $update($id, new ProductChanges(1, 7, null, null, 3)))->toThrow(CatalogException::class, 'desactivada');
    expect(fn () => $update($id, new ProductChanges(1, 7, null, null, null, 50)))->toThrow(CatalogException::class, 'marca');
    expect(fn () => $update(404, new ProductChanges(1)))->toThrow(CatalogException::class, 'no existe');
});

it('no da de baja un producto con stock, y es idempotente', function () {
    [$repo, $audit, $create, , $deactivate] = catalogKit();
    $id = $create(draft())['id'];
    $repo->stock[$id] = '3.000';

    expect(fn () => $deactivate($id, 7))->toThrow(CatalogException::class, 'stock');
    expect($repo->products[$id]->status)->toBe(ProductStatus::Active);

    $repo->stock[$id] = '0.000';
    $deactivate($id, 7);
    $deactivate($id, 7);

    expect($repo->products[$id]->status)->toBe(ProductStatus::Inactive);
    $actions = array_map(static fn ($e) => $e->action, $audit->entries);
    expect(count(array_filter($actions, static fn ($a) => $a === 'product.deactivated')))->toBe(1);
});

it('busca duplicados con el nombre normalizado', function () {
    [$repo, , $create] = catalogKit();
    $create(draft('Cuaderno A4 100 hojas'));
    $find = new FindSimilarProducts($repo);

    $matches = $find('  cuaderno   a4 100 HOJAS ');

    expect($matches)->toHaveCount(1);
    expect($matches[0]->sku)->toBe('LIB-000001');
    expect($find('Pelota de fútbol'))->toHaveCount(0);
});
