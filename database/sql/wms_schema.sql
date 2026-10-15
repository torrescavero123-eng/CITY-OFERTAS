-- =====================================================================
-- CITY OFERTAS WMS · Modelo físico de referencia · PostgreSQL 15+
-- Las migraciones de Laravel deben reproducir este modelo.
-- Tablas de paquetes que NO se redefinen aquí: roles, permissions,
-- model_has_roles, model_has_permissions, role_has_permissions
-- (spatie/laravel-permission), activity_log (spatie/laravel-activitylog),
-- notifications, jobs, failed_jobs, job_batches, sessions, cache.
-- Convenciones: tablas en snake_case plural · TIMESTAMPTZ (UTC en BD,
-- America/Lima en UI) · dinero en PEN sin IGV · cantidades en unidad base.
-- =====================================================================

CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- ---------------------------------------------------------------------
-- 1. CATÁLOGOS BASE
-- ---------------------------------------------------------------------
CREATE TABLE units (
  id SMALLSERIAL PRIMARY KEY,
  code VARCHAR(6) NOT NULL UNIQUE,
  name VARCHAR(40) NOT NULL,
  allows_decimal BOOLEAN NOT NULL DEFAULT FALSE
);

CREATE TABLE brands (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE categories (
  id BIGSERIAL PRIMARY KEY,
  parent_id BIGINT REFERENCES categories(id),
  code VARCHAR(4) NOT NULL,                       -- prefijo de SKU (solo raíz): LIB, JUG, BAZ, MEN, HOG
  name VARCHAR(80) NOT NULL,
  depth SMALLINT NOT NULL CHECK (depth BETWEEN 1 AND 3),
  sort_order INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT chk_root_has_code CHECK (parent_id IS NOT NULL OR code <> '')
);
CREATE UNIQUE INDEX uq_categories_name ON categories (COALESCE(parent_id, 0), lower(name));
CREATE UNIQUE INDEX uq_categories_root_code ON categories (code) WHERE parent_id IS NULL;

CREATE TABLE warehouses (
  id BIGSERIAL PRIMARY KEY,
  code VARCHAR(3) NOT NULL UNIQUE,                -- BOD, PIS
  name VARCHAR(60) NOT NULL,
  type VARCHAR(12) NOT NULL CHECK (type IN ('BODEGA','PISO_VENTA')),
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE users (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(40) NOT NULL UNIQUE,
  email VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,                 -- argon2id
  two_factor_secret TEXT,
  two_factor_recovery_codes TEXT,
  two_factor_confirmed_at TIMESTAMPTZ,
  default_warehouse_id BIGINT REFERENCES warehouses(id),
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  failed_attempts SMALLINT NOT NULL DEFAULT 0,
  locked_until TIMESTAMPTZ,
  last_login_at TIMESTAMPTZ,
  remember_token VARCHAR(100),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at TIMESTAMPTZ
);

-- ---------------------------------------------------------------------
-- 2. UBICACIONES
-- Código: {ALM}-Z{zona}-P{pasillo}-A{anaquel}-N{nivel}  ej. BOD-Z1-P03-A02-N2
-- Ubicaciones de paso: BOD-REC (recepción), PIS-REC (reposición en piso)
-- ---------------------------------------------------------------------
CREATE TABLE locations (
  id BIGSERIAL PRIMARY KEY,
  warehouse_id BIGINT NOT NULL REFERENCES warehouses(id),
  code VARCHAR(24) NOT NULL,
  zone VARCHAR(6),
  aisle VARCHAR(6),
  rack VARCHAR(6),
  level SMALLINT CHECK (level BETWEEN 1 AND 9),
  is_staging BOOLEAN NOT NULL DEFAULT FALSE,
  is_frozen BOOLEAN NOT NULL DEFAULT FALSE,       -- congelada durante un conteo
  max_capacity NUMERIC(14,3) CHECK (max_capacity > 0),
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (warehouse_id, code)
);
CREATE UNIQUE INDEX uq_locations_code ON locations (code);

-- ---------------------------------------------------------------------
-- 3. CATÁLOGO DE PRODUCTOS
-- ---------------------------------------------------------------------
CREATE TABLE products (
  id BIGSERIAL PRIMARY KEY,
  sku VARCHAR(16) NOT NULL UNIQUE,                -- LIB-000231 (autogenerado, inmutable)
  name VARCHAR(200) NOT NULL,
  description TEXT,
  category_id BIGINT NOT NULL REFERENCES categories(id),
  brand_id BIGINT REFERENCES brands(id),
  base_unit_id SMALLINT NOT NULL REFERENCES units(id),
  cost_avg NUMERIC(14,4) NOT NULL DEFAULT 0 CHECK (cost_avg >= 0),   -- promedio ponderado, sin IGV
  last_cost NUMERIC(14,4) CHECK (last_cost >= 0),
  reference_price NUMERIC(12,2) CHECK (reference_price >= 0),
  status VARCHAR(12) NOT NULL DEFAULT 'ACTIVE' CHECK (status IN ('ACTIVE','INACTIVE','DISCONTINUED')),
  image_path VARCHAR(255),
  version INT NOT NULL DEFAULT 1,                 -- bloqueo optimista en ediciones
  created_by BIGINT REFERENCES users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  deleted_at TIMESTAMPTZ
);
CREATE INDEX ix_products_name_trgm ON products USING gin (name gin_trgm_ops);
CREATE INDEX ix_products_category ON products (category_id) WHERE deleted_at IS NULL;
CREATE INDEX ix_products_status ON products (status);

-- Presentaciones: UND (factor 1), DOC (12), CAJA (48)...
CREATE TABLE product_units (
  id BIGSERIAL PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES products(id),
  unit_id SMALLINT NOT NULL REFERENCES units(id),
  factor_to_base NUMERIC(12,3) NOT NULL CHECK (factor_to_base > 0),
  is_purchase_default BOOLEAN NOT NULL DEFAULT FALSE,
  UNIQUE (product_id, unit_id)
);

CREATE TABLE product_barcodes (
  id BIGSERIAL PRIMARY KEY,
  product_id BIGINT NOT NULL REFERENCES products(id),
  presentation_id BIGINT REFERENCES product_units(id),  -- el código de la caja trae su factor
  barcode VARCHAR(40) NOT NULL UNIQUE,
  symbology VARCHAR(10) NOT NULL CHECK (symbology IN ('EAN13','EAN8','UPCA','CODE128','QR','INTERNAL')),
  is_primary BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX uq_one_primary_barcode ON product_barcodes (product_id) WHERE is_primary;

CREATE TABLE product_stock_policies (
  product_id BIGINT NOT NULL REFERENCES products(id),
  warehouse_id BIGINT NOT NULL REFERENCES warehouses(id),
  min_qty NUMERIC(14,3) NOT NULL DEFAULT 0 CHECK (min_qty >= 0),
  max_qty NUMERIC(14,3) CHECK (max_qty >= 0),
  reorder_qty NUMERIC(14,3) CHECK (reorder_qty > 0),
  PRIMARY KEY (product_id, warehouse_id),
  CHECK (max_qty IS NULL OR max_qty >= min_qty)
);

CREATE TABLE product_locations (                   -- ubicación sugerida (slotting)
  product_id BIGINT NOT NULL REFERENCES products(id),
  location_id BIGINT NOT NULL REFERENCES locations(id),
  is_primary BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (product_id, location_id)
);
CREATE UNIQUE INDEX uq_primary_location_per_product ON product_locations (product_id) WHERE is_primary;

-- ---------------------------------------------------------------------
-- 4. PROVEEDORES
-- ---------------------------------------------------------------------
CREATE TABLE suppliers (
  id BIGSERIAL PRIMARY KEY,
  ruc CHAR(11) NOT NULL UNIQUE CHECK (ruc ~ '^[0-9]{11}$'),
  business_name VARCHAR(160) NOT NULL,
  contact_name VARCHAR(120),
  phone VARCHAR(30),
  email VARCHAR(160),
  address VARCHAR(255),
  lead_time_days SMALLINT CHECK (lead_time_days >= 0),
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE product_suppliers (
  product_id BIGINT NOT NULL REFERENCES products(id),
  supplier_id BIGINT NOT NULL REFERENCES suppliers(id),
  supplier_sku VARCHAR(40),
  last_cost NUMERIC(14,4) CHECK (last_cost >= 0),
  is_preferred BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (product_id, supplier_id)
);

-- ---------------------------------------------------------------------
-- 5. LEDGER DE INVENTARIO (núcleo)
-- ---------------------------------------------------------------------
CREATE TABLE movement_reasons (
  id SMALLSERIAL PRIMARY KEY,
  code VARCHAR(24) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  movement_type VARCHAR(24) NOT NULL,
  requires_approval BOOLEAN NOT NULL DEFAULT FALSE,
  requires_notes BOOLEAN NOT NULL DEFAULT FALSE,
  is_active BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE stock_movements (
  id BIGSERIAL PRIMARY KEY,
  uuid UUID NOT NULL UNIQUE,
  type VARCHAR(24) NOT NULL CHECK (type IN (
    'SALDO_INICIAL','ENTRADA_COMPRA','DEVOLUCION_CLIENTE','AJUSTE_POSITIVO',
    'SALIDA_VENTA','MERMA','AJUSTE_NEGATIVO','DEVOLUCION_PROVEEDOR','TRANSFERENCIA')),
  product_id BIGINT NOT NULL REFERENCES products(id),
  from_location_id BIGINT REFERENCES locations(id),
  to_location_id BIGINT REFERENCES locations(id),
  quantity NUMERIC(14,3) NOT NULL CHECK (quantity > 0),       -- siempre en unidad base
  presentation_id BIGINT REFERENCES product_units(id),        -- lo que digitó/escaneó el usuario
  presentation_qty NUMERIC(14,3) CHECK (presentation_qty > 0),
  unit_cost NUMERIC(14,4) CHECK (unit_cost >= 0),             -- entradas: costo de compra; salidas: costo promedio vigente
  reason_id SMALLINT REFERENCES movement_reasons(id),
  source_type VARCHAR(30),                                    -- RECEIPT | TRANSFER_ORDER | ADJUSTMENT | COUNT | POS_IMPORT | OPENING
  source_id BIGINT,
  document_ref VARCHAR(60),
  notes VARCHAR(500),
  user_id BIGINT NOT NULL REFERENCES users(id),
  approved_by BIGINT REFERENCES users(id),
  idempotency_key VARCHAR(80) NOT NULL UNIQUE,
  client_created_at TIMESTAMPTZ,                              -- momento real del escaneo (modo offline)
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT chk_movement_shape CHECK (
       (type IN ('SALDO_INICIAL','ENTRADA_COMPRA','DEVOLUCION_CLIENTE','AJUSTE_POSITIVO')
          AND to_location_id IS NOT NULL AND from_location_id IS NULL)
    OR (type IN ('SALIDA_VENTA','MERMA','AJUSTE_NEGATIVO','DEVOLUCION_PROVEEDOR')
          AND from_location_id IS NOT NULL AND to_location_id IS NULL)
    OR (type = 'TRANSFERENCIA'
          AND from_location_id IS NOT NULL AND to_location_id IS NOT NULL
          AND from_location_id <> to_location_id)),
  CONSTRAINT chk_reason_required CHECK (
    type NOT IN ('MERMA','AJUSTE_POSITIVO','AJUSTE_NEGATIVO') OR reason_id IS NOT NULL),
  CONSTRAINT chk_cost_on_entry CHECK (
    type NOT IN ('ENTRADA_COMPRA','SALDO_INICIAL') OR unit_cost IS NOT NULL)
);
CREATE INDEX ix_mov_product_date ON stock_movements (product_id, created_at DESC);
CREATE INDEX ix_mov_from_loc ON stock_movements (from_location_id, created_at DESC) WHERE from_location_id IS NOT NULL;
CREATE INDEX ix_mov_to_loc ON stock_movements (to_location_id, created_at DESC) WHERE to_location_id IS NOT NULL;
CREATE INDEX ix_mov_type_date ON stock_movements (type, created_at DESC);
CREATE INDEX ix_mov_source ON stock_movements (source_type, source_id);
CREATE INDEX ix_mov_user_date ON stock_movements (user_id, created_at DESC);

-- Inmutabilidad: ni UPDATE, ni DELETE, ni TRUNCATE.
CREATE OR REPLACE FUNCTION forbid_ledger_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  RAISE EXCEPTION 'stock_movements es inmutable: registre un ajuste en lugar de modificar';
END; $$;
CREATE TRIGGER trg_ledger_no_update_delete
  BEFORE UPDATE OR DELETE ON stock_movements
  FOR EACH ROW EXECUTE FUNCTION forbid_ledger_mutation();
CREATE TRIGGER trg_ledger_no_truncate
  BEFORE TRUNCATE ON stock_movements
  FOR EACH STATEMENT EXECUTE FUNCTION forbid_ledger_mutation();

CREATE TABLE stock_balances (
  product_id BIGINT NOT NULL REFERENCES products(id),
  location_id BIGINT NOT NULL REFERENCES locations(id),
  quantity NUMERIC(14,3) NOT NULL DEFAULT 0 CHECK (quantity >= 0),   -- el stock negativo es imposible
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  PRIMARY KEY (product_id, location_id)
);
CREATE INDEX ix_balances_location ON stock_balances (location_id) WHERE quantity > 0;

-- ---------------------------------------------------------------------
-- 6. OPERACIONES
-- ---------------------------------------------------------------------
CREATE TABLE purchase_receipts (
  id BIGSERIAL PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,               -- REC-2026-000123
  supplier_id BIGINT NOT NULL REFERENCES suppliers(id),
  warehouse_id BIGINT NOT NULL REFERENCES warehouses(id),
  doc_type VARCHAR(16) NOT NULL CHECK (doc_type IN ('FACTURA','GUIA_REMISION','BOLETA','OTRO')),
  doc_number VARCHAR(30) NOT NULL,
  doc_date DATE NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'DRAFT' CHECK (status IN ('DRAFT','CONFIRMED','CANCELLED')),
  notes VARCHAR(500),
  received_by BIGINT NOT NULL REFERENCES users(id),
  confirmed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX uq_receipt_document ON purchase_receipts (supplier_id, doc_type, doc_number)
  WHERE status <> 'CANCELLED';                    -- evita registrar dos veces la misma factura

CREATE TABLE purchase_receipt_items (
  id BIGSERIAL PRIMARY KEY,
  receipt_id BIGINT NOT NULL REFERENCES purchase_receipts(id) ON DELETE CASCADE,
  product_id BIGINT NOT NULL REFERENCES products(id),
  presentation_id BIGINT REFERENCES product_units(id),
  qty_expected NUMERIC(14,3) CHECK (qty_expected >= 0),
  qty_received NUMERIC(14,3) NOT NULL CHECK (qty_received >= 0),
  unit_cost NUMERIC(14,4) NOT NULL CHECK (unit_cost >= 0),     -- por unidad base, sin IGV
  location_id BIGINT NOT NULL REFERENCES locations(id),
  movement_id BIGINT UNIQUE REFERENCES stock_movements(id)
);

CREATE TABLE transfer_orders (
  id BIGSERIAL PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,               -- TRF-2026-000045
  status VARCHAR(10) NOT NULL DEFAULT 'DRAFT' CHECK (status IN ('DRAFT','COMPLETED','CANCELLED')),
  notes VARCHAR(500),
  requested_by BIGINT NOT NULL REFERENCES users(id),
  completed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE transfer_order_items (
  id BIGSERIAL PRIMARY KEY,
  order_id BIGINT NOT NULL REFERENCES transfer_orders(id) ON DELETE CASCADE,
  product_id BIGINT NOT NULL REFERENCES products(id),
  from_location_id BIGINT NOT NULL REFERENCES locations(id),
  to_location_id BIGINT NOT NULL REFERENCES locations(id),
  quantity NUMERIC(14,3) NOT NULL CHECK (quantity > 0),
  movement_id BIGINT UNIQUE REFERENCES stock_movements(id),
  CHECK (from_location_id <> to_location_id)
);

CREATE TABLE stock_adjustment_requests (
  id BIGSERIAL PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,               -- AJU-2026-000012
  kind VARCHAR(10) NOT NULL CHECK (kind IN ('POSITIVE','NEGATIVE','WRITE_OFF')),
  product_id BIGINT NOT NULL REFERENCES products(id),
  location_id BIGINT NOT NULL REFERENCES locations(id),
  quantity NUMERIC(14,3) NOT NULL CHECK (quantity > 0),
  reason_id SMALLINT NOT NULL REFERENCES movement_reasons(id),
  notes VARCHAR(500) NOT NULL,
  evidence_path VARCHAR(255),
  status VARCHAR(10) NOT NULL DEFAULT 'PENDING' CHECK (status IN ('PENDING','APPROVED','REJECTED')),
  requested_by BIGINT NOT NULL REFERENCES users(id),
  reviewed_by BIGINT REFERENCES users(id),
  reviewed_at TIMESTAMPTZ,
  review_notes VARCHAR(500),
  movement_id BIGINT UNIQUE REFERENCES stock_movements(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CONSTRAINT chk_separation_of_duties CHECK (reviewed_by IS NULL OR reviewed_by <> requested_by),
  CONSTRAINT chk_review_complete CHECK (status = 'PENDING' OR (reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL))
);
CREATE INDEX ix_adjust_pending ON stock_adjustment_requests (created_at) WHERE status = 'PENDING';

CREATE TABLE stock_counts (
  id BIGSERIAL PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,               -- CNT-2026-000007
  warehouse_id BIGINT NOT NULL REFERENCES warehouses(id),
  scope_type VARCHAR(10) NOT NULL CHECK (scope_type IN ('FULL','ZONE','LOCATION','CATEGORY','ABC_A')),
  scope_value VARCHAR(40),
  status VARCHAR(10) NOT NULL DEFAULT 'OPEN' CHECK (status IN ('OPEN','COUNTING','REVIEW','CLOSED','CANCELLED')),
  snapshot_at TIMESTAMPTZ,                        -- instante en que se congela el stock esperado
  opened_by BIGINT NOT NULL REFERENCES users(id),
  closed_by BIGINT REFERENCES users(id),
  closed_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE stock_count_lines (
  id BIGSERIAL PRIMARY KEY,
  count_id BIGINT NOT NULL REFERENCES stock_counts(id) ON DELETE CASCADE,
  product_id BIGINT NOT NULL REFERENCES products(id),
  location_id BIGINT NOT NULL REFERENCES locations(id),
  expected_qty NUMERIC(14,3) NOT NULL CHECK (expected_qty >= 0),
  counted_qty NUMERIC(14,3) CHECK (counted_qty >= 0),
  difference NUMERIC(14,3) GENERATED ALWAYS AS (counted_qty - expected_qty) STORED,
  recount_requested BOOLEAN NOT NULL DEFAULT FALSE,
  counted_by BIGINT REFERENCES users(id),
  counted_at TIMESTAMPTZ,
  adjustment_request_id BIGINT REFERENCES stock_adjustment_requests(id),
  UNIQUE (count_id, product_id, location_id)
);

-- ---------------------------------------------------------------------
-- 7. ALERTAS, SNAPSHOTS, IMPORTACIONES Y CONFIGURACIÓN
-- ---------------------------------------------------------------------
CREATE TABLE alerts (
  id BIGSERIAL PRIMARY KEY,
  type VARCHAR(24) NOT NULL CHECK (type IN (
    'LOW_STOCK','OUT_OF_STOCK','OVERSTOCK','NO_MOVEMENT','RECONCILIATION_DIFF','LARGE_ADJUSTMENT')),
  severity VARCHAR(8) NOT NULL CHECK (severity IN ('INFO','WARNING','CRITICAL')),
  product_id BIGINT REFERENCES products(id),
  warehouse_id BIGINT REFERENCES warehouses(id),
  payload JSONB NOT NULL DEFAULT '{}',
  status VARCHAR(12) NOT NULL DEFAULT 'OPEN' CHECK (status IN ('OPEN','ACKNOWLEDGED','RESOLVED')),
  opened_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  acknowledged_by BIGINT REFERENCES users(id),
  acknowledged_at TIMESTAMPTZ,
  resolved_at TIMESTAMPTZ
);
-- Una sola alerta activa por (tipo, producto, almacén)
CREATE UNIQUE INDEX uq_alert_active ON alerts (type, COALESCE(product_id,0), COALESCE(warehouse_id,0))
  WHERE status <> 'RESOLVED';
CREATE INDEX ix_alerts_open ON alerts (severity, opened_at DESC) WHERE status <> 'RESOLVED';

-- Foto diaria (valorizado histórico y reportes rápidos). Particionada por mes.
CREATE TABLE stock_daily_snapshots (
  snapshot_date DATE NOT NULL,
  product_id BIGINT NOT NULL,
  warehouse_id BIGINT NOT NULL,
  quantity NUMERIC(14,3) NOT NULL,
  cost_avg NUMERIC(14,4) NOT NULL,
  PRIMARY KEY (snapshot_date, product_id, warehouse_id)
) PARTITION BY RANGE (snapshot_date);
CREATE TABLE stock_daily_snapshots_default PARTITION OF stock_daily_snapshots DEFAULT;
-- Un job mensual crea la partición siguiente:
-- CREATE TABLE stock_daily_snapshots_2026_11 PARTITION OF stock_daily_snapshots
--   FOR VALUES FROM ('2026-11-01') TO ('2026-12-01');

CREATE TABLE import_jobs (
  id BIGSERIAL PRIMARY KEY,
  kind VARCHAR(16) NOT NULL CHECK (kind IN ('PRODUCTS','OPENING_STOCK','POS_SALES')),
  file_path VARCHAR(255) NOT NULL,
  file_sha256 CHAR(64) NOT NULL,
  mode VARCHAR(8) NOT NULL DEFAULT 'DRY_RUN' CHECK (mode IN ('DRY_RUN','COMMIT')),
  status VARCHAR(10) NOT NULL DEFAULT 'QUEUED' CHECK (status IN ('QUEUED','RUNNING','DONE','FAILED')),
  total_rows INT, ok_rows INT, error_rows INT,
  error_report_path VARCHAR(255),
  created_by BIGINT NOT NULL REFERENCES users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  finished_at TIMESTAMPTZ
);
CREATE UNIQUE INDEX uq_import_commit_once ON import_jobs (kind, file_sha256) WHERE mode = 'COMMIT' AND status <> 'FAILED';

CREATE TABLE settings (
  key VARCHAR(60) PRIMARY KEY,
  value JSONB NOT NULL,
  updated_by BIGINT REFERENCES users(id),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 8. VISTAS, FUNCIONES Y REPORTES
-- ---------------------------------------------------------------------
-- Cada movimiento produce 1 o 2 "efectos" (delta) sobre ubicaciones.
CREATE VIEW v_movement_effects AS
SELECT id AS movement_id, product_id, to_location_id AS location_id, quantity AS delta, type, created_at
  FROM stock_movements WHERE to_location_id IS NOT NULL
UNION ALL
SELECT id, product_id, from_location_id, -quantity, type, created_at
  FROM stock_movements WHERE from_location_id IS NOT NULL;

CREATE VIEW v_kardex AS
SELECT e.movement_id, e.product_id, e.location_id, l.warehouse_id, e.type, e.delta, e.created_at,
       SUM(e.delta) OVER (PARTITION BY e.product_id, e.location_id
                          ORDER BY e.created_at, e.movement_id) AS running_balance
  FROM v_movement_effects e JOIN locations l ON l.id = e.location_id;

CREATE VIEW v_product_stock AS
SELECT b.product_id, l.warehouse_id, SUM(b.quantity) AS quantity
  FROM stock_balances b JOIN locations l ON l.id = b.location_id
 GROUP BY b.product_id, l.warehouse_id;

-- Reconciliación: debe devolver 0 filas. Si devuelve filas, se abre alerta CRITICAL.
CREATE OR REPLACE FUNCTION fn_reconcile_ledger()
RETURNS TABLE (p_product_id BIGINT, p_location_id BIGINT, p_balance NUMERIC, p_ledger NUMERIC, p_diff NUMERIC)
LANGUAGE sql STABLE AS $$
  SELECT COALESCE(b.product_id, l.product_id), COALESCE(b.location_id, l.location_id),
         COALESCE(b.quantity, 0), COALESCE(l.q, 0), COALESCE(b.quantity, 0) - COALESCE(l.q, 0)
    FROM stock_balances b
    FULL JOIN (SELECT product_id, location_id, SUM(delta) AS q
                 FROM v_movement_effects GROUP BY product_id, location_id) l
      ON l.product_id = b.product_id AND l.location_id = b.location_id
   WHERE COALESCE(b.quantity, 0) <> COALESCE(l.q, 0);
$$;

-- Rotación y cobertura (REFRESH MATERIALIZED VIEW CONCURRENTLY cada noche)
CREATE MATERIALIZED VIEW mv_product_rotation_90d AS
SELECT p.id AS product_id,
       COALESCE(SUM(m.quantity) FILTER (WHERE m.type = 'SALIDA_VENTA'), 0) AS qty_sold_90d,
       COALESCE(SUM(m.quantity * COALESCE(m.unit_cost, p.cost_avg)) FILTER (WHERE m.type = 'SALIDA_VENTA'), 0) AS value_sold_90d,
       (SELECT MAX(created_at) FROM stock_movements x WHERE x.product_id = p.id) AS last_movement_at
  FROM products p
  LEFT JOIN stock_movements m ON m.product_id = p.id AND m.created_at >= now() - INTERVAL '90 days'
 WHERE p.deleted_at IS NULL
 GROUP BY p.id;
CREATE UNIQUE INDEX uq_mv_rotation ON mv_product_rotation_90d (product_id);

-- ---------------------------------------------------------------------
-- 9. PRIVILEGIOS (principio de mínimo privilegio)
-- ---------------------------------------------------------------------
-- La app NO conecta como dueño del esquema. Rol de ejecución:
-- CREATE ROLE wms_app LOGIN PASSWORD '...';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO wms_app;
-- REVOKE UPDATE, DELETE, TRUNCATE ON stock_movements FROM wms_app;   -- defensa en profundidad
-- GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO wms_app;

-- ---------------------------------------------------------------------
-- 10. DATOS SEMILLA
-- ---------------------------------------------------------------------
INSERT INTO units (code, name, allows_decimal) VALUES
  ('UND','Unidad',FALSE), ('DOC','Docena',FALSE), ('PAQ','Paquete',FALSE),
  ('CJA','Caja',FALSE), ('PAR','Par',FALSE), ('MTR','Metro',TRUE), ('KG','Kilogramo',TRUE);

INSERT INTO warehouses (code, name, type) VALUES
  ('BOD','Bodega central','BODEGA'), ('PIS','Piso de venta','PISO_VENTA');

INSERT INTO locations (warehouse_id, code, zone, is_staging)
  SELECT id, 'BOD-REC', 'REC', TRUE FROM warehouses WHERE code = 'BOD'
  UNION ALL
  SELECT id, 'PIS-REC', 'REC', TRUE FROM warehouses WHERE code = 'PIS';

INSERT INTO categories (parent_id, code, name, depth) VALUES
  (NULL,'LIB','Librería',1), (NULL,'JUG','Juguetería',1), (NULL,'BAZ','Bazar',1),
  (NULL,'MEN','Menaje',1), (NULL,'HOG','Hogar',1);

INSERT INTO movement_reasons (code, name, movement_type, requires_approval, requires_notes) VALUES
  ('MERMA_DANIO','Producto dañado','MERMA',TRUE,TRUE),
  ('MERMA_VENCIDO','Producto vencido/deteriorado','MERMA',TRUE,TRUE),
  ('MERMA_EXTRAVIO','Extravío o faltante no explicado','MERMA',TRUE,TRUE),
  ('MERMA_ROBO','Robo / hurto','MERMA',TRUE,TRUE),
  ('MERMA_MUESTRA','Muestra o uso interno','MERMA',TRUE,TRUE),
  ('AJU_ERROR_CONTEO','Diferencia de conteo físico','AJUSTE_NEGATIVO',TRUE,TRUE),
  ('AJU_ERROR_REGISTRO','Error de registro anterior','AJUSTE_NEGATIVO',TRUE,TRUE),
  ('AJU_HALLAZGO','Hallazgo de mercadería no registrada','AJUSTE_POSITIVO',TRUE,TRUE),
  ('AJU_CONTEO_FAVOR','Sobrante de conteo físico','AJUSTE_POSITIVO',TRUE,TRUE);

INSERT INTO settings (key, value) VALUES
  ('adjustment.auto_approve_limit_pen', '0'),
  ('adjustment.large_alert_pen', '500'),
  ('count.tolerance_pct', '0'),
  ('alerts.low_stock_factor', '1.25'),
  ('alerts.no_movement_days', '90'),
  ('reports.abc_thresholds', '{"A":0.80,"B":0.95}'),
  ('locale', '{"timezone":"America/Lima","currency":"PEN","language":"es"}');

-- ---------------------------------------------------------------------
-- 11. FUNCIONES AUXILIARES
-- ---------------------------------------------------------------------
-- Permite al rol de la app refrescar la vista materializada sin ser su propietario.
CREATE OR REPLACE FUNCTION fn_refresh_rotation() RETURNS void
LANGUAGE sql SECURITY DEFINER SET search_path = public AS $$
  REFRESH MATERIALIZED VIEW CONCURRENTLY mv_product_rotation_90d;
$$;
REVOKE ALL ON FUNCTION fn_refresh_rotation() FROM PUBLIC;
