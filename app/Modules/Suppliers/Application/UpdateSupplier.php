<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Suppliers\Domain\SupplierChanges;
use App\Modules\Suppliers\Domain\SupplierException;
use App\Modules\Suppliers\Domain\SupplierRepository;
use App\Shared\Domain\Transaction;

final class UpdateSupplier
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $supplierId, SupplierChanges $changes): void
    {
        $this->transaction->run(function () use ($supplierId, $changes): void {
            $current = $this->suppliers->lock($supplierId) ?? throw SupplierException::notFound($supplierId);

            $new = $changes->fields();
            $changed = array_filter($new, static fn (mixed $v, string $k): bool => ($current->data[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH);

            if ($changed === []) {
                return;
            }

            $this->suppliers->update($supplierId, $changes);
            $this->audit->record(new AuditEntry(
                'supplier.updated', 'supplier', $supplierId, $changes->actorId,
                array_intersect_key($current->data, $changed), $changed,
            ));
        });
    }
}
