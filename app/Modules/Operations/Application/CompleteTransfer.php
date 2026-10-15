<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\MovementKind;
use App\Modules\Operations\Domain\MovementRequest;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\StockMovements;
use App\Modules\Operations\Domain\TransferLineRecord;
use App\Modules\Operations\Domain\TransferRepository;
use App\Modules\Operations\Domain\TransferStatus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Transaction;

/** Completa todas las líneas en una sola transacción: si una falla (p. ej. stock insuficiente), no se mueve nada. */
final class CompleteTransfer
{
    public function __construct(
        private readonly TransferRepository $transfers,
        private readonly StockMovements $movements,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
        private readonly Clock $clock,
    ) {}

    /** @return array{movements: int} */
    public function __invoke(int $transferId, int $actorId): array
    {
        return $this->transaction->run(function () use ($transferId, $actorId): array {
            $transfer = $this->transfers->lockTransfer($transferId) ?? throw OperationsException::notFound('La transferencia', $transferId);

            if ($transfer->status === TransferStatus::Completed) {
                return ['movements' => count($transfer->lines)]; // idempotente
            }
            if ($transfer->status === TransferStatus::Cancelled) {
                throw OperationsException::wrongState('La transferencia está anulada.');
            }

            $lines = $transfer->lines;
            usort($lines, static fn (TransferLineRecord $a, TransferLineRecord $b): int => [$a->line->productId, $a->line->fromLocationId, $a->line->toLocationId, $a->id]
                <=> [$b->line->productId, $b->line->fromLocationId, $b->line->toLocationId, $b->id]);

            foreach ($lines as $record) {
                $movementId = $this->movements->register(new MovementRequest(
                    kind: MovementKind::Transfer,
                    productId: $record->line->productId,
                    quantity: $record->line->quantity,
                    userId: $actorId,
                    idempotencyKey: $transfer->code.':L'.$record->id,
                    fromLocationId: $record->line->fromLocationId,
                    toLocationId: $record->line->toLocationId,
                    sourceType: 'TRANSFER_ORDER',
                    sourceId: $transfer->id,
                    documentRef: $transfer->code,
                ));
                $this->transfers->setLineMovement($record->id, $movementId);
            }

            $this->transfers->setTransferStatus($transferId, TransferStatus::Completed, $this->clock->now());
            $this->audit->record(new AuditEntry('transfer.completed', 'transfer', $transferId, $actorId, ['status' => 'DRAFT'], [
                'status' => 'COMPLETED', 'code' => $transfer->code, 'movements' => count($lines),
            ]));

            return ['movements' => count($lines)];
        });
    }
}
