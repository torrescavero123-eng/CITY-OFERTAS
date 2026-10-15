<?php

declare(strict_types=1);

namespace App\Modules\Locations\Infrastructure;

use App\Modules\Locations\Domain\LocationRecord;
use App\Modules\Locations\Domain\LocationRepository;
use App\Modules\Locations\Domain\WarehouseInfo;
use Illuminate\Support\Facades\DB;

final class DatabaseLocationRepository implements LocationRepository
{
    public function warehouse(int $id): ?WarehouseInfo
    {
        $row = DB::table('warehouses')->where('id', $id)->first(['id', 'code', 'is_active']);

        return $row === null ? null : new WarehouseInfo((int) $row->id, (string) $row->code, filter_var($row->is_active, FILTER_VALIDATE_BOOLEAN));
    }

    public function existingCodes(array $codes): array
    {
        $found = [];
        foreach (array_chunk($codes, 1000) as $chunk) {
            foreach (DB::table('locations')->whereIn('code', $chunk)->pluck('code') as $code) {
                $found[] = (string) $code;
            }
        }

        return $found;
    }

    public function insertMany(int $warehouseId, array $locations): void
    {
        foreach (array_chunk($locations, 500) as $chunk) {
            DB::table('locations')->insert(array_map(static fn ($l): array => [
                'warehouse_id' => $warehouseId,
                'code' => $l->code,
                'zone' => $l->zone,
                'aisle' => $l->aisle,
                'rack' => $l->rack,
                'level' => $l->level,
                'max_capacity' => $l->maxCapacity,
                'is_staging' => false,
            ], $chunk));
        }
    }

    public function lock(int $id): ?LocationRecord
    {
        $r = DB::table('locations')->where('id', $id)->lockForUpdate()->first(['id', 'code', 'is_staging', 'is_frozen', 'is_active']);

        return $r === null ? null : new LocationRecord(
            (int) $r->id, (string) $r->code,
            filter_var($r->is_staging, FILTER_VALIDATE_BOOLEAN),
            filter_var($r->is_frozen, FILTER_VALIDATE_BOOLEAN),
            filter_var($r->is_active, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function lockMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $records = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table('locations')->whereIn('id', $chunk)->orderBy('id')->lockForUpdate()->get(['id', 'code', 'is_staging', 'is_frozen', 'is_active']) as $r) {
                $records[] = new LocationRecord(
                    (int) $r->id, (string) $r->code,
                    filter_var($r->is_staging, FILTER_VALIDATE_BOOLEAN),
                    filter_var($r->is_frozen, FILTER_VALIDATE_BOOLEAN),
                    filter_var($r->is_active, FILTER_VALIDATE_BOOLEAN),
                );
            }
        }

        return $records;
    }

    public function setFrozenMany(array $ids, bool $frozen): void
    {
        foreach (array_chunk($ids, 1000) as $chunk) {
            DB::table('locations')->whereIn('id', $chunk)->update(['is_frozen' => $frozen, 'updated_at' => now()]);
        }
    }

    public function stockAt(int $id): string
    {
        $row = DB::selectOne('SELECT COALESCE(SUM(quantity), 0)::text AS total FROM stock_balances WHERE location_id = ?', [$id]);

        return bcadd((string) $row->total, '0', 3);
    }

    public function setFrozen(int $id, bool $frozen): void
    {
        DB::table('locations')->where('id', $id)->update(['is_frozen' => $frozen, 'updated_at' => now()]);
    }

    public function setActive(int $id, bool $active): void
    {
        DB::table('locations')->where('id', $id)->update(['is_active' => $active, 'updated_at' => now()]);
    }
}
