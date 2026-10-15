<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Ubicaciones congeladas por cada conteo (incluye las vacías, para detectar hallazgos).
        CREATE TABLE stock_count_locations (
          count_id BIGINT NOT NULL REFERENCES stock_counts(id) ON DELETE CASCADE,
          location_id BIGINT NOT NULL REFERENCES locations(id),
          PRIMARY KEY (count_id, location_id)
        );
        CREATE INDEX ix_count_locations_location ON stock_count_locations (location_id);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS stock_count_locations;');
    }
};
