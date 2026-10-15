<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Domain\CountBalance;
use App\Modules\Operations\Domain\CountScope;
use App\Modules\Operations\Domain\CountScopeResolver;
use App\Modules\Operations\Domain\CountScopeType;
use App\Modules\Operations\Domain\OperationsException;
use Illuminate\Support\Facades\DB;

final class DatabaseCountScopeResolver implements CountScopeResolver
{
    public function resolve(int $warehouseId, CountScopeType $type, ?string $value): CountScope
    {
        return match ($type) {
            CountScopeType::Full => $this->byLocations($warehouseId, '', []),
            CountScopeType::Zone => $this->byLocations($warehouseId, ' AND l.zone = ?', [$this->zone((string) $value)]),
            CountScopeType::Location => $this->byLocations($warehouseId, ' AND upper(l.code) = ?', [strtoupper(preg_replace('/\s+/', '', (string) $value) ?? '')]),
            CountScopeType::Category => $this->byProducts($warehouseId, $this->categoryProducts($this->categoryId((string) $value))),
            CountScopeType::AbcA => $this->byProducts($warehouseId, $this->classAProducts()),
        };
    }

    /**
     * Alcance por ubicaciones: se congelan TODAS las que coinciden (aunque estén vacías, para detectar hallazgos).
     *
     * @param  list<string>  $bindings
     */
    private function byLocations(int $warehouseId, string $filterSql, array $bindings): CountScope
    {
        $locationIds = array_map('intval', array_column(DB::select(
            "SELECT l.id FROM locations l WHERE l.warehouse_id = ? AND l.is_active = TRUE{$filterSql} ORDER BY l.id",
            [$warehouseId, ...$bindings],
        ), 'id'));

        $balances = DB::select(
            "SELECT sb.product_id, sb.location_id, sb.quantity::text AS quantity
               FROM stock_balances sb JOIN locations l ON l.id = sb.location_id
              WHERE l.warehouse_id = ? AND l.is_active = TRUE AND sb.quantity > 0{$filterSql}
              ORDER BY sb.location_id, sb.product_id",
            [$warehouseId, ...$bindings],
        );

        return new CountScope($locationIds, array_map($this->balance(...), $balances));
    }

    /**
     * Alcance por productos: solo se congelan las ubicaciones donde hay stock de esos productos.
     *
     * @param  list<int>  $productIds
     */
    private function byProducts(int $warehouseId, array $productIds): CountScope
    {
        if ($productIds === []) {
            return new CountScope([], []);
        }

        $balances = [];
        foreach (array_chunk($productIds, 1000) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            foreach (DB::select(
                "SELECT sb.product_id, sb.location_id, sb.quantity::text AS quantity
                   FROM stock_balances sb JOIN locations l ON l.id = sb.location_id
                  WHERE l.warehouse_id = ? AND l.is_active = TRUE AND sb.quantity > 0 AND sb.product_id IN ({$in})
                  ORDER BY sb.location_id, sb.product_id",
                [$warehouseId, ...$chunk],
            ) as $row) {
                $balances[] = $this->balance($row);
            }
        }

        return new CountScope(array_values(array_unique(array_map(static fn (CountBalance $b): int => $b->locationId, $balances))), $balances);
    }

    private function balance(object $row): CountBalance
    {
        return new CountBalance((int) $row->product_id, (int) $row->location_id, Quantity::of((string) $row->quantity));
    }

    private function zone(string $value): string
    {
        $v = strtoupper(trim($value));

        return ctype_digit($v) ? 'Z'.$v : $v;
    }

    private function categoryId(string $value): int
    {
        if (! ctype_digit(trim($value))) {
            throw OperationsException::invalid('La categoría del conteo debe indicarse por su número.');
        }

        return (int) trim($value);
    }

    /** @return list<int> productos de la categoría y de todas sus subcategorías */
    private function categoryProducts(int $categoryId): array
    {
        $rows = DB::select(
            'WITH RECURSIVE tree AS (
                SELECT id FROM categories WHERE id = ?
                UNION ALL
                SELECT c.id FROM categories c JOIN tree t ON c.parent_id = t.id
             )
             SELECT p.id FROM products p WHERE p.category_id IN (SELECT id FROM tree) AND p.deleted_at IS NULL',
            [$categoryId],
        );

        return array_map('intval', array_column($rows, 'id'));
    }

    /** @return list<int> productos de clase A: los que explican el 80 % del valor vendido en 90 días */
    private function classAProducts(): array
    {
        $rows = DB::select(
            'WITH ranked AS (
                SELECT product_id, value_sold_90d,
                       SUM(value_sold_90d) OVER (ORDER BY value_sold_90d DESC, product_id) AS cum,
                       SUM(value_sold_90d) OVER () AS total
                  FROM mv_product_rotation_90d WHERE value_sold_90d > 0
             )
             SELECT product_id FROM ranked WHERE total > 0 AND (cum - value_sold_90d) < 0.8 * total',
        );

        return array_map('intval', array_column($rows, 'product_id'));
    }
}
