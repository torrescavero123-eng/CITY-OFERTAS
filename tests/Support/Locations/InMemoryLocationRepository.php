<?php

declare(strict_types=1);

namespace Tests\Support\Locations;

use App\Modules\Locations\Domain\LocationRecord;
use App\Modules\Locations\Domain\LocationRepository;
use App\Modules\Locations\Domain\WarehouseInfo;

final class InMemoryLocationRepository implements LocationRepository
{
    /** @var array<int, WarehouseInfo> */
    public array $warehouses = [];

    /** @var array<int, array{record: LocationRecord, warehouse: int}> */
    public array $rows = [];

    /** @var array<int, string> */
    public array $stock = [];

    public function warehouse(int $id): ?WarehouseInfo
    {
        return $this->warehouses[$id] ?? null;
    }

    public function existingCodes(array $codes): array
    {
        $existing = array_map(static fn (array $r): string => $r['record']->code, $this->rows);

        return array_values(array_intersect($codes, $existing));
    }

    public function insertMany(int $warehouseId, array $locations): void
    {
        foreach ($locations as $l) {
            $id = count($this->rows) + 1;
            $this->rows[$id] = ['record' => new LocationRecord($id, $l->code, false, false, true), 'warehouse' => $warehouseId];
        }
    }

    public function lock(int $id): ?LocationRecord
    {
        return ($this->rows[$id] ?? null)['record'] ?? null;
    }

    public function lockMany(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (isset($this->rows[$id])) {
                $out[] = $this->rows[$id]['record'];
            }
        }

        return $out;
    }

    public function setFrozenMany(array $ids, bool $frozen): void
    {
        foreach ($ids as $id) {
            $this->setFrozen($id, $frozen);
        }
    }

    public function stockAt(int $id): string
    {
        return $this->stock[$id] ?? '0.000';
    }

    public function setFrozen(int $id, bool $frozen): void
    {
        $r = $this->rows[$id]['record'];
        $this->rows[$id]['record'] = new LocationRecord($r->id, $r->code, $r->isStaging, $frozen, $r->isActive);
    }

    public function setActive(int $id, bool $active): void
    {
        $r = $this->rows[$id]['record'];
        $this->rows[$id]['record'] = new LocationRecord($r->id, $r->code, $r->isStaging, $r->isFrozen, $active);
    }

    public function markStaging(int $id): void
    {
        $r = $this->rows[$id]['record'];
        $this->rows[$id]['record'] = new LocationRecord($r->id, $r->code, true, $r->isFrozen, $r->isActive);
    }
}
