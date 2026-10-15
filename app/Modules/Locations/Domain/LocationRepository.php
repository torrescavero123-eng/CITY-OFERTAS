<?php

declare(strict_types=1);

namespace App\Modules\Locations\Domain;

interface LocationRepository
{
    public function warehouse(int $id): ?WarehouseInfo;

    /**
     * @param  list<string>  $codes
     * @return list<string> los códigos que ya existen
     */
    public function existingCodes(array $codes): array;

    /** @param list<NewLocation> $locations */
    public function insertMany(int $warehouseId, array $locations): void;

    /** Bloquea la fila (FOR UPDATE). */
    public function lock(int $id): ?LocationRecord;

    /** Stock total en la ubicación, como decimal en texto. */
    public function stockAt(int $id): string;

    /**
     * Bloquea varias ubicaciones en orden de id (FOR UPDATE).
     *
     * @param  list<int>  $ids
     * @return list<LocationRecord>
     */
    public function lockMany(array $ids): array;

    /** @param list<int> $ids */
    public function setFrozenMany(array $ids, bool $frozen): void;

    public function setFrozen(int $id, bool $frozen): void;

    public function setActive(int $id, bool $active): void;
}
