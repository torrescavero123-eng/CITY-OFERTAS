<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Inventario valorizado: unidades y valor (al costo promedio) por producto y almacén.
        CREATE VIEW v_report_valued_stock AS
        SELECT (p.id * 1000 + w.id) AS row_key,
               p.id AS product_id, p.sku, p.name AS product_name, c.name AS category_name,
               w.id AS warehouse_id, w.name AS warehouse_name,
               SUM(sb.quantity) AS qty, p.cost_avg, SUM(sb.quantity) * p.cost_avg AS value
          FROM stock_balances sb
          JOIN locations l ON l.id = sb.location_id
          JOIN warehouses w ON w.id = l.warehouse_id
          JOIN products p ON p.id = sb.product_id AND p.deleted_at IS NULL
          LEFT JOIN categories c ON c.id = p.category_id
         WHERE sb.quantity > 0
         GROUP BY p.id, p.sku, p.name, p.cost_avg, c.name, w.id, w.name;

        -- Rotación y clasificación ABC (últimos 90 días, por valor vendido al costo):
        -- A = los que suman el 80 % del valor, B = hasta el 95 %, C = el resto, SIN_VENTAS = no se vendió nada.
        CREATE VIEW v_report_abc AS
        WITH sales AS (
            SELECT product_id, SUM(quantity) AS qty_90d, SUM(quantity * COALESCE(unit_cost, 0)) AS value_90d
              FROM stock_movements
             WHERE type = 'SALIDA_VENTA' AND created_at >= now() - INTERVAL '90 days'
             GROUP BY product_id
        ), stock AS (
            SELECT product_id, SUM(quantity) AS qty FROM stock_balances GROUP BY product_id
        ), ranked AS (
            SELECT s.product_id, s.qty_90d, s.value_90d,
                   SUM(s.value_90d) OVER (ORDER BY s.value_90d DESC, s.product_id) AS cum_value,
                   SUM(s.value_90d) OVER () AS total_value
              FROM sales s WHERE s.value_90d > 0
        )
        SELECT p.id AS product_id, p.sku, p.name AS product_name, c.name AS category_name,
               COALESCE(r.qty_90d, 0) AS qty_90d, COALESCE(r.value_90d, 0) AS value_90d,
               CASE WHEN r.product_id IS NULL THEN NULL ELSE ROUND(100 * r.value_90d / NULLIF(r.total_value, 0), 2) END AS share_pct,
               CASE WHEN r.product_id IS NULL THEN 'SIN_VENTAS'
                    WHEN (r.cum_value - r.value_90d) < 0.80 * r.total_value THEN 'A'
                    WHEN (r.cum_value - r.value_90d) < 0.95 * r.total_value THEN 'B'
                    ELSE 'C' END AS abc_class,
               COALESCE(st.qty, 0) AS stock,
               CASE WHEN COALESCE(r.qty_90d, 0) > 0 THEN ROUND(COALESCE(st.qty, 0) / (r.qty_90d / 90.0), 0) END AS cover_days,
               p.cost_avg
          FROM products p
          LEFT JOIN ranked r ON r.product_id = p.id
          LEFT JOIN stock st ON st.product_id = p.id
          LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.deleted_at IS NULL AND p.status = 'ACTIVE';

        -- Mermas y ajustes negativos, con motivo, responsable y valor al costo.
        CREATE VIEW v_report_waste AS
        SELECT m.id, m.created_at, m.type, p.sku, p.name AS product_name, l.code AS location_code,
               r.name AS reason_name, m.quantity, COALESCE(m.unit_cost, 0) AS unit_cost,
               m.quantity * COALESCE(m.unit_cost, 0) AS value,
               u.name AS user_name, a.name AS approver_name, m.document_ref, m.notes
          FROM stock_movements m
          JOIN products p ON p.id = m.product_id
          JOIN locations l ON l.id = m.from_location_id
          LEFT JOIN movement_reasons r ON r.id = m.reason_id
          JOIN users u ON u.id = m.user_id
          LEFT JOIN users a ON a.id = m.approved_by
         WHERE m.type IN ('MERMA', 'AJUSTE_NEGATIVO');
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP VIEW IF EXISTS v_report_waste; DROP VIEW IF EXISTS v_report_abc; DROP VIEW IF EXISTS v_report_valued_stock;');
    }
};
