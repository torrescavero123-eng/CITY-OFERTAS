<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\Operations\Domain\SalesTarget;
use App\Modules\Operations\Domain\SalesTargets;
use Illuminate\Support\Facades\DB;

final class DatabaseSalesTargets implements SalesTargets
{
    public function resolve(string $code): ?SalesTarget
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $product = DB::selectOne('SELECT p.id, p.sku FROM products p WHERE p.sku = upper(?) AND p.deleted_at IS NULL', [$code])
            ?? DB::selectOne(
                'SELECT p.id, p.sku FROM product_barcodes b JOIN products p ON p.id = b.product_id AND p.deleted_at IS NULL WHERE b.barcode = ? LIMIT 1',
                [$code],
            );
        if ($product === null) {
            return null;
        }

        // 1) ubicación principal del producto en el piso de venta; 2) si no hay, la del piso con más stock.
        $location = DB::selectOne(
            "SELECT l.id, l.code, COALESCE(sb.quantity, 0)::text AS available
               FROM product_locations pl
               JOIN locations l ON l.id = pl.location_id AND l.is_active = TRUE
               JOIN warehouses w ON w.id = l.warehouse_id AND w.type = 'PISO_VENTA'
               LEFT JOIN stock_balances sb ON sb.product_id = pl.product_id AND sb.location_id = l.id
              WHERE pl.product_id = ? AND pl.is_primary = TRUE
              LIMIT 1",
            [(int) $product->id],
        ) ?? DB::selectOne(
            "SELECT l.id, l.code, sb.quantity::text AS available
               FROM stock_balances sb
               JOIN locations l ON l.id = sb.location_id AND l.is_active = TRUE
               JOIN warehouses w ON w.id = l.warehouse_id AND w.type = 'PISO_VENTA'
              WHERE sb.product_id = ? AND sb.quantity > 0
              ORDER BY sb.quantity DESC, l.id
              LIMIT 1",
            [(int) $product->id],
        );
        if ($location === null) {
            return null;
        }

        return new SalesTarget((int) $product->id, (string) $product->sku, (int) $location->id, (string) $location->code, bcadd((string) $location->available, '0', 3));
    }
}
