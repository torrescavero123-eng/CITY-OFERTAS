<?php

declare(strict_types=1);

namespace Tests\Support\Suppliers;

use App\Modules\Suppliers\Domain\NewSupplier;
use App\Modules\Suppliers\Domain\SupplierChanges;
use App\Modules\Suppliers\Domain\SupplierRecord;
use App\Modules\Suppliers\Domain\SupplierRepository;

final class InMemorySupplierRepository implements SupplierRepository
{
    /** @var array<int, array{ruc: string, active: bool, data: array<string, scalar|null>}> */
    public array $rows = [];

    /** @var list<int> */
    public array $products = [10, 11];

    /** @var array<string, array{sku: ?string, cost: ?string, preferred: bool}> clave "producto:proveedor" */
    public array $links = [];

    public function rucExists(string $ruc): bool
    {
        foreach ($this->rows as $r) {
            if ($r['ruc'] === $ruc) {
                return true;
            }
        }

        return false;
    }

    public function insert(NewSupplier $supplier): int
    {
        $id = count($this->rows) + 1;
        $this->rows[$id] = ['ruc' => $supplier->ruc->value, 'active' => true, 'data' => [
            'business_name' => $supplier->businessName, 'contact_name' => $supplier->contactName, 'phone' => $supplier->phone,
            'email' => $supplier->email, 'address' => $supplier->address, 'lead_time_days' => $supplier->leadTimeDays,
        ]];

        return $id;
    }

    public function lock(int $id): ?SupplierRecord
    {
        $r = $this->rows[$id] ?? null;

        return $r === null ? null : new SupplierRecord($id, $r['ruc'], (string) $r['data']['business_name'], $r['active'], $r['data']);
    }

    public function update(int $id, SupplierChanges $changes): void
    {
        $this->rows[$id]['data'] = $changes->fields() + $this->rows[$id]['data'];
    }

    public function setActive(int $id, bool $active): void
    {
        $this->rows[$id]['active'] = $active;
    }

    public function productExists(int $productId): bool
    {
        return in_array($productId, $this->products, true);
    }

    public function linkProduct(int $productId, int $supplierId, ?string $supplierSku, ?string $lastCost, bool $preferred): void
    {
        $this->links["{$productId}:{$supplierId}"] = ['sku' => $supplierSku, 'cost' => $lastCost, 'preferred' => $preferred];
    }

    public function clearPreferred(int $productId): void
    {
        foreach ($this->links as $k => $l) {
            if (str_starts_with($k, "{$productId}:")) {
                $this->links[$k]['preferred'] = false;
            }
        }
    }
}
