# ADR-0003 · Bloqueo por producto e idempotencia

**Estado:** aceptada (corrige el borrador inicial, que bloqueaba por fila de saldo)

**Contexto.** Dos almaceneros pueden restar la última unidad a la vez, y la PWA reenvía movimientos tras cortes de red. Además, el costo promedio ponderado depende del stock total del producto.

**Decisión.**
1. `RegisterMovement` bloquea primero la fila del producto (`SELECT ... FOR UPDATE`). Serializa todos los movimientos **del mismo producto** y protege el cálculo del costo promedio; productos distintos no se esperan entre sí.
2. Después consulta `idempotency_key`: si existe con el mismo `uuid`, devuelve el movimiento original (`duplicate = true`); si existe con otro `uuid`, rechaza (`IDEMPOTENCY_KEY_REUSED`).
3. El bloqueo de la fila de saldo (`FOR UPDATE`) se mantiene como defensa en profundidad, y `UPDATE ... WHERE quantity >= ?` garantiza que nunca quede negativo.
4. Ubicaciones: bloqueo compartido (`FOR SHARE`) para que un congelamiento por conteo concurrente espere.
5. Reintento automático de la transacción ante deadlock (3 intentos): el caso de uso es idempotente.

**Regla para operaciones multi-producto (transferencias por lote):** procesar y bloquear los productos en orden ascendente de id para evitar deadlocks.
