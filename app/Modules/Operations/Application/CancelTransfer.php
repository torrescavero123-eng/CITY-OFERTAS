<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\TransferRepository;
use App\Modules\Operations\Domain\TransferStatus;
use App\Shared\Domain\Transaction;

final class CancelTransfer
{
    public function __construct(
        private readonly TransferRepository $transfers,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $transferId, int $actorId): void
    {
        $this->transaction->run(function () use ($transferId, $actorId): void {
            $transfer = $this->transfers->lockTransfer($transferId) ?? throw OperationsException::notFound('La transferencia', $transferId);

            if ($transfer->status === TransferStatus::Cancelled) {
                return;
            }
            if ($transfer->status === TransferStatus::Completed) {
                throw OperationsException::wrongState('Una transferencia completada no se anula: haga la transferencia inversa.');
            }

            $this->transfers->setTransferStatus($transferId, TransferStatus::Cancelled);
            $this->audit->record(new AuditEntry('transfer.cancelled', 'transfer', $transferId, $actorId, ['status' => 'DRAFT'], ['status' => 'CANCELLED']));
        });
    }
}
