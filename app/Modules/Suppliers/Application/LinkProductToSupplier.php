<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Suppliers\Domain\SupplierException;
use App\Modules\Suppliers\Domain\SupplierRepository;
use App\Shared\Domain\Transaction;

final class LinkProductToSupplier
{
    public function __construct(
        private readonly SupplierRepository $suppliers,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $productId, int $supplierId, ?string $supplierSku = null, ?string $lastCost = null, bool $preferred = false, ?int $actorId = null): void
    {
        $sku = $supplierSku === null || trim($supplierSku) === '' ? null : trim($supplierSku);
        if ($sku !== null && mb_strlen($sku) > 40) {
            throw SupplierException::invalid('El código del proveedor no puede superar 40 caracteres.');
        }
        if ($lastCost !== null && preg_match('/^\d{1,10}(\.\d{1,4})?$/', $lastCost) !== 1) {
            throw SupplierException::invalid('El último costo no es válido.');
        }

        $this->transaction->run(function () use ($productId, $supplierId, $sku, $lastCost, $preferred, $actorId): void {
            $supplier = $this->suppliers->lock($supplierId) ?? throw SupplierException::notFound($supplierId);
            if (! $supplier->isActive) {
                throw SupplierException::inactive($supplier->businessName);
            }
            if (! $this->suppliers->productExists($productId)) {
                throw SupplierException::productNotFound($productId);
            }

            if ($preferred) {
                $this->suppliers->clearPreferred($productId); // un solo proveedor preferido por producto
            }
            $this->suppliers->linkProduct($productId, $supplierId, $sku, $lastCost === null ? null : bcadd($lastCost, '0', 4), $preferred);

            $this->audit->record(new AuditEntry('supplier.product_linked', 'supplier', $supplierId, $actorId, null, [
                'product_id' => $productId, 'supplier_sku' => $sku, 'last_cost' => $lastCost, 'preferred' => $preferred,
            ]));
        });
    }
}
