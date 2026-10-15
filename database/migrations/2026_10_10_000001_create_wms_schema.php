<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Esquema base del WMS. Las vistas, triggers, particiones y CHECKs complejos no se expresan
 * con Blueprint, por lo que la fuente de verdad es database/sql/wms_schema.sql.
 * Una vez fusionada, esta migración NO se edita: los cambios van en migraciones nuevas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared((string) file_get_contents(database_path('sql/wms_schema.sql')));
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
        DROP MATERIALIZED VIEW IF EXISTS mv_product_rotation_90d;
        DROP VIEW IF EXISTS v_kardex, v_product_stock, v_movement_effects;
        DROP FUNCTION IF EXISTS fn_reconcile_ledger(), fn_refresh_rotation(), forbid_ledger_mutation() CASCADE;
        DROP TABLE IF EXISTS
          settings, import_jobs, stock_daily_snapshots, alerts,
          stock_count_lines, stock_counts, stock_adjustment_requests,
          transfer_order_items, transfer_orders, purchase_receipt_items, purchase_receipts,
          stock_balances, stock_movements, movement_reasons,
          product_suppliers, suppliers, product_locations, product_stock_policies,
          product_barcodes, product_units, products, locations, users,
          warehouses, categories, brands, units
        CASCADE;
        SQL);
    }
};
