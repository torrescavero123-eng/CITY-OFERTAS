# ADR-0004 · Auditoría propia e inmutable (reemplaza spatie/laravel-activitylog)

**Estado:** aceptada

**Contexto.** El plan inicial usaba `spatie/laravel-activitylog`. Esa tabla es modificable por cualquiera con acceso a la BD y su API cambia entre versiones mayores.

**Decisión.** Tabla propia `audit_logs`, append-only: trigger que rechaza UPDATE/DELETE/TRUNCATE y `REVOKE` de esos privilegios al rol `wms_app` (misma filosofía que el ledger, ADR-0002). Se escribe desde los casos de uso mediante el puerto `AuditTrail`, dentro de la misma transacción que el cambio auditado: si el cambio se revierte, la entrada también.

**Reglas.** Nunca se auditan contraseñas ni secretos. Las actualizaciones guardan solo los campos que cambiaron (antes y después). Retención mínima 5 años (confirmar con el contador).

**Consecuencias.** Se elimina la dependencia `spatie/laravel-activitylog`.
