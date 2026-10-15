<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Correlativos anuales de documentos (REC-2026-000001, TRF-..., AJU-..., CNT-...).
        CREATE TABLE document_counters (
          prefix VARCHAR(4) NOT NULL,
          year SMALLINT NOT NULL,
          last_value INT NOT NULL DEFAULT 0 CHECK (last_value >= 0),
          PRIMARY KEY (prefix, year)
        );

        -- Lo que el usuario escaneó/digitó (p. ej. 5 cajas) además de la cantidad en unidad base.
        ALTER TABLE purchase_receipt_items ADD COLUMN presentation_qty NUMERIC(14,3) CHECK (presentation_qty > 0);

        -- El orden real de los movimientos de un producto es el de su id (se asigna dentro del bloqueo del producto).
        CREATE OR REPLACE VIEW v_kardex AS
        SELECT e.movement_id, e.product_id, e.location_id, l.warehouse_id, e.type, e.delta, e.created_at,
               SUM(e.delta) OVER (PARTITION BY e.product_id, e.location_id
                                  ORDER BY e.movement_id) AS running_balance
          FROM v_movement_effects e JOIN locations l ON l.id = e.location_id;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE purchase_receipt_items DROP COLUMN IF EXISTS presentation_qty; DROP TABLE IF EXISTS document_counters;');
    }
};
