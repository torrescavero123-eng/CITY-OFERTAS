<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use App\Modules\Catalog\Domain\CatalogRepository;
use App\Modules\Catalog\Domain\CategoryInfo;
use App\Modules\Catalog\Domain\NewProduct;
use App\Modules\Catalog\Domain\ProductChanges;
use App\Modules\Catalog\Domain\ProductLookup;
use App\Modules\Catalog\Domain\ProductRecord;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Domain\SimilarProduct;

final class InMemoryCatalogRepository implements CatalogRepository
{
    /** @var array<int, CategoryInfo> */
    public array $categories = [];

    /** @var list<int> */
    public array $brands = [];

    /** @var list<int> */
    public array $units = [];

    /** @var list<int> */
    public array $warehouses = [];

    /** @var array<string, int> código => id de producto */
    public array $barcodes = [];

    /** @var array<int, ProductRecord> */
    public array $products = [];

    /** @var array<int, NewProduct> */
    public array $inserted = [];

    /** @var array<int, string> */
    public array $stock = [];

    public function category(int $id): ?CategoryInfo
    {
        return $this->categories[$id] ?? null;
    }

    public function brandExists(int $id): bool
    {
        return in_array($id, $this->brands, true);
    }

    public function existingUnitIds(array $ids): array
    {
        return array_values(array_intersect($ids, $this->units));
    }

    public function existingWarehouseIds(array $ids): array
    {
        return array_values(array_intersect($ids, $this->warehouses));
    }

    public function barcodeOwner(string $barcode): ?int
    {
        return $this->barcodes[$barcode] ?? null;
    }

    public function insert(NewProduct $product, string $sku): int
    {
        $id = count($this->products) + 1;
        $this->products[$id] = new ProductRecord(
            $id, $sku, $product->name, $product->description, $product->categoryId,
            $product->brandId, $product->referencePrice, ProductStatus::Active, 1,
        );
        $this->inserted[$id] = $product;
        foreach ($product->barcodes as $b) {
            $this->barcodes[$b->barcode->value] = $id;
        }

        return $id;
    }

    public function lock(int $productId): ?ProductRecord
    {
        return $this->products[$productId] ?? null;
    }

    public function update(int $productId, ProductChanges $changes): void
    {
        $p = $this->products[$productId];
        $this->products[$productId] = new ProductRecord(
            $p->id, $p->sku, $changes->name ?? $p->name, $changes->description ?? $p->description,
            $changes->categoryId ?? $p->categoryId, $changes->brandId ?? $p->brandId,
            $changes->referencePrice ?? $p->referencePrice, $p->status, $p->version + 1,
        );
    }

    public function setStatus(int $productId, ProductStatus $status): void
    {
        $p = $this->products[$productId];
        $this->products[$productId] = new ProductRecord(
            $p->id, $p->sku, $p->name, $p->description, $p->categoryId, $p->brandId,
            $p->referencePrice, $status, $p->version + 1,
        );
    }

    public function totalStock(int $productId): string
    {
        return $this->stock[$productId] ?? '0.000';
    }

    public function similarNames(string $name, float $threshold, int $limit): array
    {
        $out = [];
        foreach ($this->products as $p) {
            similar_text(mb_strtolower($p->name), mb_strtolower($name), $percent);
            if ($percent / 100 >= $threshold) {
                $out[] = new SimilarProduct($p->id, $p->sku, $p->name, round($percent / 100, 2));
            }
        }

        return array_slice($out, 0, $limit);
    }

    public function lookup(string $code): ?ProductLookup
    {
        return null; // la búsqueda real se prueba contra PostgreSQL
    }
}
