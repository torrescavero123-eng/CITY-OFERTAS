<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\AdjustmentRepository;
use App\Modules\Operations\Domain\AdjustmentStatus;
use App\Modules\Operations\Domain\MovementRequest;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\StockMovements;
use App\Shared\Domain\Transaction;

final class ApproveAdjustment
{
    public function __construct(
        private readonly AdjustmentRepository $adjustments,
        private readonly StockMovements $movements,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    /** @return int id del movimiento generado */
    public function __invoke(int $adjustmentId, int $reviewerId, ?string $reviewNotes = null): int
    {
        return $this->transaction->run(function () use ($adjustmentId, $reviewerId, $reviewNotes): int {
            $record = $this->adjustments->lockAdjustment($adjustmentId) ?? throw OperationsException::notFound('La solicitud de ajuste', $adjustmentId);

            if ($record->status === AdjustmentStatus::Approved && $record->movementId !== null) {
                return $record->movementId; // idempotente
            }
            if ($record->status === AdjustmentStatus::Rejected) {
                throw OperationsException::wrongState('La solicitud ya fue rechazada.');
            }
            if ($record->request->requestedBy === $reviewerId) {
                throw OperationsException::separationOfDuties();
            }

            $a = $record->request;
            $movementId = $this->movements->register(new MovementRequest(
                kind: $a->kind->movementKind(),
                productId: $a->productId,
                quantity: $a->quantity,
                userId: $a->requestedBy,
                idempotencyKey: $record->code.':APPROVE',
                fromLocationId: $a->kind === AdjustmentKind::Positive ? null : $a->locationId,
                toLocationId: $a->kind === AdjustmentKind::Positive ? $a->locationId : null,
                reasonId: $a->reasonId,
                sourceType: 'ADJUSTMENT',
                sourceId: $record->id,
                documentRef: $record->code,
                notes: $a->notes,
                approvedBy: $reviewerId,
            ));

            $this->adjustments->markApproved($adjustmentId, $reviewerId, $movementId, $reviewNotes);
            $this->audit->record(new AuditEntry('adjustment.approved', 'adjustment', $adjustmentId, $reviewerId, ['status' => 'PENDING'], [
                'status' => 'APPROVED', 'code' => $record->code, 'movement_id' => $movementId,
            ]));

            return $movementId;
        });
    }
}
