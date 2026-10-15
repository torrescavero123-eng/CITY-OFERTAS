<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Infrastructure;

use App\Modules\Suppliers\Domain\NewSupplier;
use App\Modules\Suppliers\Domain\SupplierChanges;
use App\Modules\Suppliers\Domain\SupplierRecord;
use App\Modules\Suppliers\Domain\SupplierRepository;
use Illuminate\Support\Facades\DB;

final class DatabaseSupplierRepository implements SupplierRepository
{
    public function rucExists(string $ruc): bool
    {
        return DB::table('suppliers')->where('ruc', $ruc)->exists();
    }

    public function insert(NewSupplier $supplier): int
    {
        return (int) DB::table('suppliers')->insertGetId([
            'ruc' => $supplier->ruc->value,
            'business_name' => $supplier->businessName,
            'contact_name' => $supplier->contactName,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'address' => $supplier->address,
            'lead_time_days' => $supplier->leadTimeDays,
            'is_active' => true,
        ], 'id');
    }

    public function lock(int $id): ?SupplierRecord
    {
        $r = DB::table('suppliers')->where('id', $id)->lockForUpdate()->first();

        return $r === null ? null : new SupplierRecord(
            (int) $r->id, trim((string) $r->ruc), (string) $r->business_name,
            filter_var($r->is_active, FILTER_VALIDATE_BOOLEAN),
            [
                'business_name' => $r->business_name, 'contact_name' => $r->contact_name, 'phone' => $r->phone,
                'email' => $r->email, 'address' => $r->address,
                'lead_time_days' => $r->lead_time_days === null ? null : (int) $r->lead_time_days,
            ],
        );
    }

    public function update(int $id, SupplierChanges $changes): void
    {
        DB::table('suppliers')->where('id', $id)->update($changes->fields() + ['updated_at' => now()]);
    }

    public function setActive(int $id, bool $active): void
    {
        DB::table('suppliers')->where('id', $id)->update(['is_active' => $active, 'updated_at' => now()]);
    }

    public function productExists(int $productId): bool
    {
        return DB::table('products')->where('id', $productId)->whereNull('deleted_at')->exists();
    }

    public function linkProduct(int $productId, int $supplierId, ?string $supplierSku, ?string $lastCost, bool $preferred): void
    {
        DB::statement(
            'INSERT INTO product_suppliers (product_id, supplier_id, supplier_sku, last_cost, is_preferred)
             VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (product_id, supplier_id)
             DO UPDATE SET supplier_sku = EXCLUDED.supplier_sku,
                           last_cost = COALESCE(EXCLUDED.last_cost, product_suppliers.last_cost),
                           is_preferred = EXCLUDED.is_preferred',
            [$productId, $supplierId, $supplierSku, $lastCost, $preferred ? 'true' : 'false'],
        );
    }

    public function clearPreferred(int $productId): void
    {
        DB::table('product_suppliers')->where('product_id', $productId)->update(['is_preferred' => false]);
    }
}
