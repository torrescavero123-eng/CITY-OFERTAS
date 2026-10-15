<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

/** Consultas de solo lectura que necesitan las operaciones. */
interface OperationsLookup
{
    public function supplierIsActive(int $supplierId): bool;

    public function warehouseIsActive(int $warehouseId): bool;

    /** Almacén al que pertenece la ubicación, o null si no existe. */
    public function locationWarehouseId(int $locationId): ?int;

    public function reason(int $reasonId): ?ReasonInfo;

    /** Id del motivo por su código (p. ej. AJU_ERROR_CONTEO), o null. */
    public function reasonIdByCode(string $code): ?int;

    /** Costo promedio vigente del producto (4 decimales), o null si el producto no existe. */
    public function averageCost(int $productId): ?string;

    /** Límite en soles para registrar un ajuste sin aprobación ("0.00" = no se permite). */
    public function directAdjustmentLimit(): string;
}
