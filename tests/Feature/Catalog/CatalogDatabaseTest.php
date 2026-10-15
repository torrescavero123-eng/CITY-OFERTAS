<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\CreateProduct;
use App\Modules\Catalog\Application\DeactivateProduct;
use App\Modules\Catalog\Application\FindSimilarProducts;
use App\Modules\Catalog\Application\LookupProduct;
use App\Modules\Catalog\Application\UpdateProduct;
use App\Modules\Catalog\Domain\Barcode;
use App\Modules\Catalog\Domain\BarcodeInput;
use App\Modules\Catalog\Domain\CatalogException;
use App\Modules\Catalog\Domain\Factor;
use App\Modules\Catalog\Domain\NewProduct;
use App\Modules\Catalog\Domain\PresentationInput;
use App\Modules\Catalog\Domain\ProductChanges;
use App\Modules\Catalog\Domain\StockPolicy;
use App\Modules\Catalog\Domain\Symbology;
use App\Modules\Inventory\Application\RegisterMovement;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\Support\MovementFactory as M;

function unitId(string $code): int
{
    return (int) DB::table('units')->where('code', $code)->value('id');
}

function categoryId(string $code): int
{
    return (int) DB::table('categories')->whereNull('parent_id')->where('code', $code)->value('id');
}

function newProduct(string $name, int $actor, array $over = []): NewProduct
{
    return new NewProduct(
        $name, $over['category'] ?? categoryId('LIB'), unitId('UND'), $actor, null, null, null,
        $over['presentations'] ?? [], $over['barcodes'] ?? [], $over['policies'] ?? [],
    );
}

it('crea producto con presentaciones, códigos y políticas en una sola transacción', function () {
    $actor = Fixtures::user();
    $bodega = (int) DB::table('warehouses')->where('code', 'BOD')->value('id');

    $result = app(CreateProduct::class)(newProduct('Cuaderno A4 100 hojas', $actor, [
        'presentations' => [new PresentationInput(unitId('DOC'), Factor::of(12), true)],
        'barcodes' => [
            new BarcodeInput(Barcode::of('4006381333931', Symbology::Ean13)),
            new BarcodeInput(Barcode::of('CAJA-000231', Symbology::Internal), unitId('DOC')),
        ],
        'policies' => [new StockPolicy($bodega, 10, 100, 50)],
    ]));

    expect($result['sku'])->toBe('LIB-000001');
    expect(DB::table('product_units')->where('product_id', $result['id'])->count())->toBe(2);
    expect(DB::table('product_barcodes')->where('product_id', $result['id'])->count())->toBe(2);
    expect(DB::table('product_barcodes')->where('product_id', $result['id'])->where('is_primary', true)->count())->toBe(1);
    expect(DB::table('product_stock_policies')->where('product_id', $result['id'])->count())->toBe(1);
    expect(DB::table('audit_logs')->where('action', 'product.created')->where('entity_id', $result['id'])->count())->toBe(1);
});

it('los SKU son correlativos por categoría raíz, también desde subcategorías', function () {
    $actor = Fixtures::user();
    $lib = categoryId('LIB');
    $childId = (int) DB::table('categories')->insertGetId(
        ['parent_id' => $lib, 'code' => 'CUA', 'name' => 'Cuadernos', 'depth' => 2], 'id',
    );
    $create = app(CreateProduct::class);

    $a = $create(newProduct('Producto uno', $actor));
    $b = $create(newProduct('Producto dos', $actor, ['category' => $childId]));
    $c = $create(newProduct('Pelota grande', $actor, ['category' => categoryId('JUG')]));

    expect($a['sku'])->toBe('LIB-000001');
    expect($b['sku'])->toBe('LIB-000002');
    expect($c['sku'])->toBe('JUG-000001');
});

it('rechaza un código de barras ya usado y no deja el producto a medias', function () {
    $actor = Fixtures::user();
    $create = app(CreateProduct::class);
    $code = new BarcodeInput(Barcode::of('4006381333931', Symbology::Ean13));
    $create(newProduct('Primero', $actor, ['barcodes' => [$code]]));
    $before = DB::table('products')->count();

    expect(fn () => $create(newProduct('Segundo', $actor, ['barcodes' => [$code]])))->toThrow(CatalogException::class, 'ya pertenece');
    expect(DB::table('products')->count())->toBe($before);
});

it('escanear devuelve producto, factor de la presentación y stock por ubicación', function () {
    $actor = Fixtures::user();
    $created = app(CreateProduct::class)(newProduct('Lápiz HB', $actor, [
        'presentations' => [new PresentationInput(unitId('DOC'), Factor::of(12))],
        'barcodes' => [
            new BarcodeInput(Barcode::of('4006381333931', Symbology::Ean13)),
            new BarcodeInput(Barcode::of('DOCENA-0001', Symbology::Internal), unitId('DOC')),
        ],
    ]));
    $bodega = Fixtures::location('BOD');
    app(RegisterMovement::class)(M::purchase($created['id'], $bodega, 10, '2', $actor));
    $lookup = app(LookupProduct::class);

    $unit = $lookup('4006381333931');
    $dozen = $lookup('DOCENA-0001');
    $bySku = $lookup('lib-000001');

    expect($unit->presentationFactor)->toBe('1.000');
    expect($dozen->presentationFactor)->toBe('12.000');
    expect($bySku->productId)->toBe($created['id']);
    expect($unit->stock)->toHaveCount(1);
    expect($unit->stock[0]['quantity'])->toBe('10.000');
    expect($unit->costAvg)->toBe('2.0000');
    expect($lookup('no-existe'))->toBeNull();
    expect($lookup('   '))->toBeNull();
});

it('detecta posibles duplicados por similitud de nombre', function () {
    $actor = Fixtures::user();
    app(CreateProduct::class)(newProduct('Cuaderno A4 100 hojas', $actor));

    $matches = app(FindSimilarProducts::class)('  CUADERNO   A4 100 HOJAS ');

    expect($matches)->toHaveCount(1);
    expect($matches[0]->similarity >= 0.8)->toBeTrue();
    expect(app(FindSimilarProducts::class)('Pelota de fútbol número 5'))->toHaveCount(0);
});

it('actualiza con control optimista de versión', function () {
    $actor = Fixtures::user();
    $id = app(CreateProduct::class)(newProduct('Cuaderno A4', $actor))['id'];
    $update = app(UpdateProduct::class);

    $update($id, new ProductChanges(1, $actor, 'Cuaderno A4 rayado'));

    expect(DB::table('products')->where('id', $id)->value('name'))->toBe('Cuaderno A4 rayado');
    expect((int) DB::table('products')->where('id', $id)->value('version'))->toBe(2);
    expect(fn () => $update($id, new ProductChanges(1, $actor, 'Otro nombre')))->toThrow(CatalogException::class, 'modificado por otra persona');
});

it('no da de baja con stock; con stock en cero sí', function () {
    $actor = Fixtures::user();
    $id = app(CreateProduct::class)(newProduct('Borrador', $actor))['id'];
    $loc = Fixtures::location('BOD');
    $register = app(RegisterMovement::class);
    $register(M::purchase($id, $loc, 5, '1', $actor));

    expect(fn () => app(DeactivateProduct::class)($id, $actor))->toThrow(CatalogException::class, 'stock');

    $register(M::sale($id, $loc, 5, $actor));
    app(DeactivateProduct::class)($id, $actor);

    expect(DB::table('products')->where('id', $id)->value('status'))->toBe('INACTIVE');
});
