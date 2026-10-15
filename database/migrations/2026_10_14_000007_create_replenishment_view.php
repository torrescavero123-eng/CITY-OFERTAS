<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Estado de stock de cada producto contra su política (mínimo/máximo/reposición) por almacén.
        -- Es la fuente de las alertas, de la reposición sugerida y del tablero: siempre al día, sin procesos intermedios.
        CREATE VIEW v_replenishment AS
        SELECT (pol.product_id * 1000 + pol.warehouse_id) AS row_key,
               p.id AS product_id, p.sku, p.name AS product_name, p.cost_avg,
               pol.warehouse_id, w.name AS warehouse_name,
               COALESCE(st.qty, 0) AS stock,
               pol.min_qty, pol.max_qty, pol.reorder_qty,
               CASE
                 WHEN COALESCE(st.qty, 0) <= 0 THEN 'OUT'
                 WHEN COALESCE(st.qty, 0) <= pol.min_qty THEN 'LOW'
                 WHEN COALESCE(st.qty, 0) <= pol.min_qty * 1.25 THEN 'NEAR'
                 WHEN pol.max_qty IS NOT NULL AND COALESCE(st.qty, 0) > pol.max_qty THEN 'OVER'
                 ELSE 'OK'
               END AS status,
               CASE
                 WHEN COALESCE(st.qty, 0) <= pol.min_qty
                   THEN GREATEST(COALESCE(pol.max_qty, pol.min_qty + COALESCE(pol.reorder_qty, 0)) - COALESCE(st.qty, 0), COALESCE(pol.reorder_qty, 0))
                 ELSE 0
               END AS suggested_qty,
               (SELECT COALESCE(SUM(m.quantity), 0) FROM stock_movements m
                 WHERE m.product_id = p.id AND m.type = 'SALIDA_VENTA' AND m.created_at >= now() - INTERVAL '30 days') AS sold_30d,
               (SELECT s.business_name FROM product_suppliers ps JOIN suppliers s ON s.id = ps.supplier_id
                 WHERE ps.product_id = p.id AND ps.is_preferred AND s.is_active LIMIT 1) AS supplier_name,
               (SELECT s.lead_time_days FROM product_suppliers ps JOIN suppliers s ON s.id = ps.supplier_id
                 WHERE ps.product_id = p.id AND ps.is_preferred AND s.is_active LIMIT 1) AS lead_time_days
          FROM product_stock_policies pol
          JOIN products p ON p.id = pol.product_id AND p.deleted_at IS NULL AND p.status = 'ACTIVE'
          JOIN warehouses w ON w.id = pol.warehouse_id
          LEFT JOIN (SELECT sb.product_id, l.warehouse_id, SUM(sb.quantity) AS qty
                       FROM stock_balances sb JOIN locations l ON l.id = sb.location_id
                      GROUP BY sb.product_id, l.warehouse_id) st
            ON st.product_id = pol.product_id AND st.warehouse_id = pol.warehouse_id;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS v_replenishment;');
    }
};
