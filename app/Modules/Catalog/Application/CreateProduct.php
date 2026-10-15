<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Catalog\Domain\CatalogException;
use App\Modules\Catalog\Domain\CatalogRepository;
use App\Modules\Catalog\Domain\NewProduct;
use App\Modules\Catalog\Domain\SkuSequence;
use App\Shared\Domain\Transaction;

final class CreateProduct
{
    public function __construct(
        private readonly CatalogRepository $catalog,
        private readonly SkuSequence $skus,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    /** @return array{id: int, sku: string} */
    public function __invoke(NewProduct $product): array
    {
        return $this->transaction->run(function () use ($product): array {
            $category = $this->catalog->category($product->categoryId);
            if ($category === null || ! $category->isActive) {
                throw CatalogException::categoryNotFound($product->categoryId);
            }
            if ($product->brandId !== null && ! $this->catalog->brandExists($product->brandId)) {
                throw CatalogException::brandNotFound($product->brandId);
            }

            $unitIds = array_values(array_unique(array_map(
                static fn ($p): int => $p->unitId,
                $product->presentations,
            )));
            $existingUnits = $this->catalog->existingUnitIds($unitIds);
            foreach ($unitIds as $unitId) {
                if (! in_array($unitId, $existingUnits, true)) {
                    throw CatalogException::unitNotFound($unitId);
                }
            }

            $warehouseIds = array_map(static fn ($p): int => $p->warehouseId, $product->policies);
            $existingWarehouses = $this->catalog->existingWarehouseIds($warehouseIds);
            foreach ($warehouseIds as $warehouseId) {
                if (! in_array($warehouseId, $existingWarehouses, true)) {
                    throw CatalogException::warehouseNotFound($warehouseId);
                }
            }

            foreach ($product->barcodes as $b) {
                if ($this->catalog->barcodeOwner($b->barcode->value) !== null) {
                    throw CatalogException::duplicateBarcode($b->barcode->value);
                }
            }

            $sku = $this->skus->next($category->rootCode);
            $id = $this->catalog->insert($product, $sku);

            $this->audit->record(new AuditEntry('product.created', 'product', $id, $product->actorId, null, [
                'sku' => $sku, 'name' => $product->name, 'category_id' => $product->categoryId,
                'base_unit_id' => $product->baseUnitId, 'barcodes' => count($product->barcodes),
            ]));

            return ['id' => $id, 'sku' => $sku];
        });
    }
}
