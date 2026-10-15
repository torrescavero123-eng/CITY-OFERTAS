<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\Operations\Domain\OperationsLookup;
use App\Modules\Operations\Domain\ReasonInfo;
use Illuminate\Support\Facades\DB;

final class DatabaseOperationsLookup implements OperationsLookup
{
    public function supplierIsActive(int $supplierId): bool
    {
        return DB::table('suppliers')->where('id', $supplierId)->where('is_active', true)->exists();
    }

    public function warehouseIsActive(int $warehouseId): bool
    {
        return DB::table('warehouses')->where('id', $warehouseId)->where('is_active', true)->exists();
    }

    public function locationWarehouseId(int $locationId): ?int
    {
        $id = DB::table('locations')->where('id', $locationId)->value('warehouse_id');

        return $id === null ? null : (int) $id;
    }

    public function reason(int $reasonId): ?ReasonInfo
    {
        $r = DB::table('movement_reasons')->where('id', $reasonId)->first();

        return $r === null ? null : new ReasonInfo(
            (int) $r->id, (string) $r->movement_type,
            filter_var($r->requires_notes, FILTER_VALIDATE_BOOLEAN), filter_var($r->is_active, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function reasonIdByCode(string $code): ?int
    {
        $id = DB::table('movement_reasons')->where('code', $code)->where('is_active', true)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function averageCost(int $productId): ?string
    {
        $cost = DB::table('products')->where('id', $productId)->whereNull('deleted_at')->value('cost_avg');

        return $cost === null ? null : bcadd((string) $cost, '0', 4);
    }

    public function directAdjustmentLimit(): string
    {
        $raw = DB::table('settings')->where('key', 'adjustment.auto_approve_limit_pen')->value('value');
        $value = $raw === null ? 0 : json_decode((string) $raw, true);

        return is_numeric($value) && (float) $value >= 0 ? bcadd((string) $value, '0', 2) : '0.00';
    }
}
