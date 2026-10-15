<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Domain;

interface SupplierRepository
{
    public function rucExists(string $ruc): bool;

    public function insert(NewSupplier $supplier): int;

    /** Bloquea la fila (FOR UPDATE). */
    public function lock(int $id): ?SupplierRecord;

    public function update(int $id, SupplierChanges $changes): void;

    public function setActive(int $id, bool $active): void;

    public function productExists(int $productId): bool;

    /** Crea o actualiza el vínculo producto-proveedor. */
    public function linkProduct(int $productId, int $supplierId, ?string $supplierSku, ?string $lastCost, bool $preferred): void;

    /** Deja a ningún proveedor como preferido para ese producto. */
    public function clearPreferred(int $productId): void;
}
