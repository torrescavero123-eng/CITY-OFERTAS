<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Suppliers\Domain\SupplierException;
use App\Modules\Suppliers\Domain\SupplierRepository;
use App\Shared\Domain\Transaction;

/** Un proveedor nunca se borra (puede tener recepciones): se activa o desactiva. */
final class ChangeSupplierState
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function deactivate(int $supplierId, ?int $actorId = null): void
    {
        $this->set($supplierId, false, $actorId, 'supplier.deactivated');
    }

    public function activate(int $supplierId, ?int $actorId = null): void
    {
        $this->set($supplierId, true, $actorId, 'supplier.activated');
    }

    private function set(int $supplierId, bool $active, ?int $actorId, string $action): void
    {
        $this->transaction->run(function () use ($supplierId, $active, $actorId, $action): void {
            $supplier = $this->suppliers->lock($supplierId) ?? throw SupplierException::notFound($supplierId);

            if ($supplier->isActive === $active) {
                return; // idempotente
            }

            $this->suppliers->setActive($supplierId, $active);
            $this->audit->record(new AuditEntry($action, 'supplier', $supplierId, $actorId, ['is_active' => ! $active], ['is_active' => $active]));
        });
    }
}
