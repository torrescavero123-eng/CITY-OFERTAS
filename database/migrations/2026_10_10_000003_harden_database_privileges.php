<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mínimo privilegio: la app corre como `wms_app` (sin UPDATE/DELETE/TRUNCATE sobre el ledger).
 * Si el rol no existe (p. ej. en CI), la migración no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        DO $$
        BEGIN
          IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'wms_app') THEN
            GRANT USAGE ON SCHEMA public TO wms_app;
            GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO wms_app;
            GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO wms_app;
            REVOKE UPDATE, DELETE, TRUNCATE ON stock_movements FROM wms_app;
            GRANT EXECUTE ON FUNCTION fn_refresh_rotation() TO wms_app;
            ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO wms_app;
            ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO wms_app;
          END IF;
        END $$;
        SQL);
    }

    public function down(): void
    {
        // Intencionalmente vacío: no se revocan privilegios en un rollback.
    }
};
