<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Suppliers\Domain\NewSupplier;
use App\Modules\Suppliers\Domain\SupplierException;
use App\Modules\Suppliers\Domain\SupplierRepository;
use App\Shared\Domain\Transaction;

final class CreateSupplier
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(NewSupplier $supplier): int
    {
        return $this->transaction->run(function () use ($supplier): int {
            if ($this->suppliers->rucExists($supplier->ruc->value)) {
                throw SupplierException::rucTaken($supplier->ruc->value);
            }

            $id = $this->suppliers->insert($supplier);
            $this->audit->record(new AuditEntry('supplier.created', 'supplier', $id, $supplier->actorId, null, [
                'ruc' => $supplier->ruc->value, 'business_name' => $supplier->businessName,
            ]));

            return $id;
        });
    }
}
