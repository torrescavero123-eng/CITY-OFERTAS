# ADR-0005 · Costeo promedio ponderado

**Estado:** aceptada, pendiente de confirmación con el contador (supuesto S-05)

**Decisión.** Costo promedio ponderado **global por producto** (no por ubicación), en PEN **sin IGV**, 4 decimales, redondeo half-up:

    nuevo = (Q_total x promedio_actual + q x costo_compra) / (Q_total + q)

- Solo `ENTRADA_COMPRA` y `SALDO_INICIAL` (con costo) recalculan el promedio.
- Devoluciones de cliente y ajustes positivos entran al promedio vigente (no lo alteran).
- Salidas, mermas, ajustes negativos y devoluciones a proveedor se valorizan al promedio vigente y lo guardan en `unit_cost`.
- Aritmética decimal con bcmath; nunca `float`.

**Alternativa descartada por ahora:** PEPS/FIFO (requiere capas de costo por lote; entra en Fase 2 junto con lotes y vencimientos).
