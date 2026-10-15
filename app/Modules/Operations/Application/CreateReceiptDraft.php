<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\DocumentNumbers;
use App\Modules\Operations\Domain\NewReceipt;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\OperationsLookup;
use App\Modules\Operations\Domain\ReceiptRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Transaction;

final class CreateReceiptDraft
{
    public function __construct(
        private readonly ReceiptRepository $receipts,
        private readonly OperationsLookup $lookup,
        private readonly DocumentNumbers $numbers,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
        private readonly Clock $clock,
    ) {}

    /** @return array{id: int, code: string} */
    public function __invoke(NewReceipt $receipt): array
    {
        return $this->transaction->run(function () use ($receipt): array {
            if (! $this->lookup->supplierIsActive($receipt->supplierId)) {
                throw OperationsException::supplierInactive();
            }
            if (! $this->lookup->warehouseIsActive($receipt->warehouseId)) {
                throw OperationsException::warehouseNotFound();
            }
            // Tolerancia de un día por la diferencia horaria con UTC.
            if ($receipt->docDate > $this->clock->now()->modify('+1 day')->format('Y-m-d')) {
                throw OperationsException::invalid('La fecha del documento no puede ser futura.');
            }
            foreach ($receipt->lines as $line) {
                if ($this->lookup->locationWarehouseId($line->locationId) !== $receipt->warehouseId) {
                    throw OperationsException::locationNotInWarehouse($line->locationId);
                }
            }
            if ($this->receipts->activeDocumentExists($receipt->supplierId, $receipt->docType, $receipt->docNumber)) {
                throw OperationsException::duplicateDocument($receipt->docType->value, $receipt->docNumber);
            }

            $code = $this->numbers->next('REC');
            $id = $this->receipts->insertReceipt($receipt, $code);

            $this->audit->record(new AuditEntry('receipt.drafted', 'receipt', $id, $receipt->receivedBy, null, [
                'code' => $code, 'supplier_id' => $receipt->supplierId, 'document' => $receipt->docType->value.' '.$receipt->docNumber,
                'lines' => count($receipt->lines),
            ]));

            return ['id' => $id, 'code' => $code];
        });
    }
}
