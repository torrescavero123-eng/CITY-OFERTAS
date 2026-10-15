<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
        -- Correlativo de SKU por prefijo de categoría raíz (se incrementa de forma atómica).
        CREATE TABLE sku_counters (
          category_code VARCHAR(4) PRIMARY KEY,
          last_value INT NOT NULL DEFAULT 0 CHECK (last_value >= 0)
        );

        -- Auditoría propia, append-only (misma filosofía que el ledger).
        CREATE TABLE audit_logs (
          id BIGSERIAL PRIMARY KEY,
          actor_id BIGINT REFERENCES users(id),
          action VARCHAR(60) NOT NULL,
          entity_type VARCHAR(40) NOT NULL,
          entity_id BIGINT,
          old_values JSONB,
          new_values JSONB,
          ip VARCHAR(45),
          user_agent VARCHAR(255),
          created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        );
        CREATE INDEX ix_audit_entity ON audit_logs (entity_type, entity_id, created_at DESC);
        CREATE INDEX ix_audit_actor ON audit_logs (actor_id, created_at DESC);
        CREATE INDEX ix_audit_action ON audit_logs (action, created_at DESC);

        CREATE OR REPLACE FUNCTION forbid_audit_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN
          RAISE EXCEPTION 'audit_logs es inmutable: la auditoría no se modifica ni se borra';
        END; $$;
        CREATE TRIGGER trg_audit_no_update_delete
          BEFORE UPDATE OR DELETE ON audit_logs
          FOR EACH ROW EXECUTE FUNCTION forbid_audit_mutation();
        CREATE TRIGGER trg_audit_no_truncate
          BEFORE TRUNCATE ON audit_logs
          FOR EACH STATEMENT EXECUTE FUNCTION forbid_audit_mutation();

        DO $$
        BEGIN
          IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'wms_app') THEN
            REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM wms_app;
          END IF;
        END $$;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS audit_logs, sku_counters CASCADE; DROP FUNCTION IF EXISTS forbid_audit_mutation() CASCADE;');
    }
};
