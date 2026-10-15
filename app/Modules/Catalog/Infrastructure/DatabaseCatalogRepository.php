<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\Catalog\Domain\CatalogException;
use App\Modules\Catalog\Domain\CatalogRepository;
use App\Modules\Catalog\Domain\CategoryInfo;
use App\Modules\Catalog\Domain\NewProduct;
use App\Modules\Catalog\Domain\ProductChanges;
use App\Modules\Catalog\Domain\ProductLookup;
use App\Modules\Catalog\Domain\ProductRecord;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Domain\SimilarProduct;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DatabaseCatalogRepository implements CatalogRepository
{
    public function category(int $id): ?CategoryInfo
    {
        $self = DB::table('categories')->where('id', $id)->first(['id', 'is_active']);
        if ($self === null) {
            return null;
        }

        $root = DB::selectOne(
            'WITH RECURSIVE up AS (
                SELECT id, parent_id, code FROM categories WHERE id = ?
                UNION ALL
                SELECT c.id, c.parent_id, c.code FROM categories c JOIN up ON c.id = up.parent_id
             )
             SELECT code FROM up WHERE parent_id IS NULL',
            [$id],
        );

        return new CategoryInfo(
            (int) $self->id,
            (string) ($root->code ?? ''),
            filter_var($self->is_active, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function brandExists(int $id): bool
    {
        return DB::table('brands')->where('id', $id)->exists();
    }

    public function existingUnitIds(array $ids): array
    {
        return $ids === [] ? [] : array_map('intval', DB::table('units')->whereIn('id', $ids)->pluck('id')->all());
    }

    public function existingWarehouseIds(array $ids): array
    {
        return $ids === [] ? [] : array_map('intval', DB::table('warehouses')->whereIn('id', $ids)->pluck('id')->all());
    }

    public function barcodeOwner(string $barcode): ?int
    {
        $id = DB::table('product_barcodes')->where('barcode', $barcode)->value('product_id');

        return $id === null ? null : (int) $id;
    }

    public function insert(NewProduct $product, string $sku): int
    {
        try {
            $productId = (int) DB::table('products')->insertGetId([
                'sku' => $sku,
                'name' => $product->name,
                'description' => $product->description,
                'category_id' => $product->categoryId,
                'brand_id' => $product->brandId,
                'base_unit_id' => $product->baseUnitId,
                'reference_price' => $product->referencePrice,
                'status' => ProductStatus::Active->value,
                'created_by' => $product->actorId,
            ], 'id');

            $presentationIds = [];
            foreach ($product->presentations as $p) {
                $presentationIds[$p->unitId] = (int) DB::table('product_units')->insertGetId([
                    'product_id' => $productId,
                    'unit_id' => $p->unitId,
                    'factor_to_base' => $p->factor->value,
                    'is_purchase_default' => $p->isPurchaseDefault,
                ], 'id');
            }

            foreach ($product->barcodes as $b) {
                DB::table('product_barcodes')->insert([
                    'product_id' => $productId,
                    'presentation_id' => $presentationIds[$b->presentationUnitId ?? $product->baseUnitId],
                    'barcode' => $b->barcode->value,
                    'symbology' => $b->barcode->symbology->value,
                    'is_primary' => $b->isPrimary,
                ]);
            }

            foreach ($product->policies as $policy) {
                DB::table('product_stock_policies')->insert([
                    'product_id' => $productId,
                    'warehouse_id' => $policy->warehouseId,
                    'min_qty' => $policy->minQty,
                    'max_qty' => $policy->maxQty,
                    'reorder_qty' => $policy->reorderQty,
                ]);
            }

            return $productId;
        } catch (UniqueConstraintViolationException $e) {
            // Carrera entre dos altas con el mismo código: la BD es la última defensa.
            throw CatalogException::duplicateBarcode('(verifique los códigos ingresados)');
        }
    }

    public function lock(int $productId): ?ProductRecord
    {
        $r = DB::table('products')->where('id', $productId)->whereNull('deleted_at')->lockForUpdate()->first();

        return $r === null ? null : new ProductRecord(
            (int) $r->id, (string) $r->sku, (string) $r->name, $r->description,
            (int) $r->category_id, $r->brand_id === null ? null : (int) $r->brand_id,
            $r->reference_price === null ? null : (string) $r->reference_price,
            ProductStatus::from((string) $r->status), (int) $r->version,
        );
    }

    public function update(int $productId, ProductChanges $changes): void
    {
        DB::table('products')->where('id', $productId)->update($changes->fields() + [
            'version' => DB::raw('version + 1'),
            'updated_at' => now(),
        ]);
    }

    public function setStatus(int $productId, ProductStatus $status): void
    {
        DB::table('products')->where('id', $productId)->update([
            'status' => $status->value,
            'version' => DB::raw('version + 1'),
            'updated_at' => now(),
        ]);
    }

    public function totalStock(int $productId): string
    {
        $row = DB::selectOne(
            'SELECT COALESCE(SUM(quantity), 0)::text AS total FROM stock_balances WHERE product_id = ?',
            [$productId],
        );

        return bcadd((string) $row->total, '0', 3);
    }

    public function similarNames(string $name, float $threshold, int $limit): array
    {
        $rows = DB::select(
            'SELECT id, sku, name, similarity(lower(name), lower(?)) AS score
               FROM products
              WHERE deleted_at IS NULL AND similarity(lower(name), lower(?)) >= ?
              ORDER BY score DESC, id
              LIMIT ?',
            [$name, $name, $threshold, $limit],
        );

        return array_map(
            static fn (object $r): SimilarProduct => new SimilarProduct((int) $r->id, (string) $r->sku, (string) $r->name, round((float) $r->score, 2)),
            $rows,
        );
    }

    public function lookup(string $code): ?ProductLookup
    {
        $hit = DB::selectOne(
            'SELECT p.id, p.sku, p.name, p.status, p.cost_avg, pu.id AS presentation_id, pu.factor_to_base
               FROM product_barcodes b
               JOIN products p ON p.id = b.product_id AND p.deleted_at IS NULL
               LEFT JOIN product_units pu ON pu.id = b.presentation_id
              WHERE b.barcode = ?',
            [$code],
        ) ?? DB::selectOne(
            'SELECT p.id, p.sku, p.name, p.status, p.cost_avg, NULL AS presentation_id, 1.000 AS factor_to_base
               FROM products p WHERE p.sku = upper(?) AND p.deleted_at IS NULL',
            [$code],
        );

        if ($hit === null) {
            return null;
        }

        $stock = array_map(static fn (object $s): array => [
            'location_id' => (int) $s->location_id,
            'location_code' => (string) $s->location_code,
            'warehouse' => (string) $s->warehouse,
            'quantity' => bcadd((string) $s->quantity, '0', 3),
        ], DB::select(
            'SELECT l.id AS location_id, l.code AS location_code, w.code AS warehouse, sb.quantity
               FROM stock_balances sb
               JOIN locations l ON l.id = sb.location_id
               JOIN warehouses w ON w.id = l.warehouse_id
              WHERE sb.product_id = ? AND sb.quantity > 0
              ORDER BY l.code',
            [(int) $hit->id],
        ));

        return new ProductLookup(
            (int) $hit->id, (string) $hit->sku, (string) $hit->name, (string) $hit->status,
            $hit->presentation_id === null ? null : (int) $hit->presentation_id,
            bcadd((string) $hit->factor_to_base, '0', 3),
            bcadd((string) $hit->cost_avg, '0', 4),
            $stock,
        );
    }
}
