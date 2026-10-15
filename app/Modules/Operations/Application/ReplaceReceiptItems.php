<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Operations\Domain\NewReceipt;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\OperationsLookup;
use App\Modules\Operations\Domain\ReceiptLine;
use App\Modules\Operations\Domain\ReceiptRepository;
use App\Modules\Operations\Domain\ReceiptStatus;
use App\Shared\Domain\Transaction;

/** Reemplaza las líneas de un BORRADOR (guardado automático mientras se escanea). */
final class ReplaceReceiptItems
{
    public function __construct(
        private readonly ReceiptRepository $receipts,
        private readonly OperationsLookup $lookup,
        private readonly Transaction $transaction,
    ) {}

    /** @param list<ReceiptLine> $lines */
    public function __invoke(int $receiptId, array $lines): void
    {
        if ($lines === [] || count($lines) > NewReceipt::MAX_LINES) {
            throw OperationsException::invalid('La recepción debe tener entre 1 y '.NewReceipt::MAX_LINES.' líneas.');
        }

        $this->transaction->run(function () use ($receiptId, $lines): void {
            $receipt = $this->receipts->lockReceipt($receiptId) ?? throw OperationsException::notFound('La recepción', $receiptId);

            if ($receipt->status !== ReceiptStatus::Draft) {
                throw OperationsException::wrongState('Solo se pueden modificar recepciones en borrador.');
            }
            foreach ($lines as $line) {
                if ($this->lookup->locationWarehouseId($line->locationId) !== $receipt->warehouseId) {
                    throw OperationsException::locationNotInWarehouse($line->locationId);
                }
            }

            $this->receipts->replaceReceiptItems($receiptId, $lines);
        });
    }
}
