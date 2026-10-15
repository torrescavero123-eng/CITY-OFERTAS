<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\ReceiptRepository;
use App\Modules\Operations\Domain\ReceiptStatus;
use App\Shared\Domain\Transaction;

/** Solo se anula un borrador. Una recepción confirmada se corrige con un ajuste o una devolución. */
final class CancelReceipt
{
    public function __construct(
        private readonly ReceiptRepository $receipts,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $receiptId, int $actorId): void
    {
        $this->transaction->run(function () use ($receiptId, $actorId): void {
            $receipt = $this->receipts->lockReceipt($receiptId) ?? throw OperationsException::notFound('La recepción', $receiptId);

            if ($receipt->status === ReceiptStatus::Cancelled) {
                return;
            }
            if ($receipt->status === ReceiptStatus::Confirmed) {
                throw OperationsException::wrongState('Una recepción confirmada no se anula: corríjala con un ajuste o una devolución.');
            }

            $this->receipts->setReceiptStatus($receiptId, ReceiptStatus::Cancelled);
            $this->audit->record(new AuditEntry('receipt.cancelled', 'receipt', $receiptId, $actorId, ['status' => 'DRAFT'], ['status' => 'CANCELLED']));
        });
    }
}
