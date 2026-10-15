# ADR-0002 · Ledger inmutable

**Estado:** aceptada

**Contexto.** El problema central del negocio son los descuadres entre stock físico y sistema. Editar saldos directamente destruye la trazabilidad.

**Decisión.** El stock se deriva de `stock_movements` (append-only). `stock_balances` es una tabla de lectura rápida que solo modifica `RegisterMovement`, dentro de la misma transacción. Corregir un error = registrar otro movimiento (ajuste con motivo y aprobación).

**Defensas en la BD (no solo en el código):** trigger que rechaza UPDATE/DELETE/TRUNCATE; `REVOKE` de esos privilegios al rol `wms_app`; `CHECK (quantity >= 0)` en saldos; `CHECK` de forma del movimiento según su tipo; `fn_reconcile_ledger()` ejecutada cada noche (debe devolver 0 filas).

**Consecuencias.** Historial completo y auditable; las correcciones dejan rastro; la reconciliación detecta cualquier escritura fuera del caso de uso.
