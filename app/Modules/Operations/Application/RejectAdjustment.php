<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\AdjustmentRepository;
use App\Modules\Operations\Domain\AdjustmentStatus;
use App\Modules\Operations\Domain\OperationsException;
use App\Shared\Domain\Transaction;

final class RejectAdjustment
{
    public function __construct(
        private readonly AdjustmentRepository $adjustments,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $adjustmentId, int $reviewerId, string $reviewNotes): void
    {
        $notes = trim($reviewNotes);
        if (mb_strlen($notes) < 5 || mb_strlen($notes) > 500) {
            throw OperationsException::invalid('Explique por qué se rechaza (entre 5 y 500 caracteres).');
        }

        $this->transaction->run(function () use ($adjustmentId, $reviewerId, $notes): void {
            $record = $this->adjustments->lockAdjustment($adjustmentId) ?? throw OperationsException::notFound('La solicitud de ajuste', $adjustmentId);

            if ($record->status === AdjustmentStatus::Rejected) {
                return;
            }
            if ($record->status === AdjustmentStatus::Approved) {
                throw OperationsException::wrongState('La solicitud ya fue aprobada y no se puede rechazar.');
            }
            if ($record->request->requestedBy === $reviewerId) {
                throw OperationsException::separationOfDuties();
            }

            $this->adjustments->markRejected($adjustmentId, $reviewerId, $notes);
            $this->audit->record(new AuditEntry('adjustment.rejected', 'adjustment', $adjustmentId, $reviewerId, ['status' => 'PENDING'], [
                'status' => 'REJECTED', 'code' => $record->code, 'review_notes' => $notes,
            ]));
        });
    }
}
