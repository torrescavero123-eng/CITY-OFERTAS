<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\MovementKind;
use App\Modules\Operations\Domain\MovementRequest;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\ReceiptItemRecord;
use App\Modules\Operations\Domain\ReceiptRepository;
use App\Modules\Operations\Domain\ReceiptStatus;
use App\Modules\Operations\Domain\StockMovements;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Transaction;

/** Confirmar = registrar todas las entradas en UNA transacción: o entra todo o no entra nada. */
final class ConfirmReceipt
{
    public function __construct(
        private readonly ReceiptRepository $receipts,
        private readonly StockMovements $movements,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
        private readonly Clock $clock,
    ) {}

    /** @return array{movements: int, discrepancies: int} */
    public function __invoke(int $receiptId, int $actorId): array
    {
        return $this->transaction->run(function () use ($receiptId, $actorId): array {
            $receipt = $this->receipts->lockReceipt($receiptId) ?? throw OperationsException::notFound('La recepción', $receiptId);

            $discrepancies = count(array_filter($receipt->items, static fn (ReceiptItemRecord $i): bool => $i->line->hasDiscrepancy()));

            if ($receipt->status === ReceiptStatus::Confirmed) {
                return [ // idempotente: no se vuelve a registrar nada
                    'movements' => count(array_filter($receipt->items, static fn (ReceiptItemRecord $i): bool => $i->movementId !== null)),
                    'discrepancies' => $discrepancies,
                ];
            }
            if ($receipt->status === ReceiptStatus::Cancelled) {
                throw OperationsException::wrongState('La recepción está anulada.');
            }

            $items = array_values(array_filter($receipt->items, static fn (ReceiptItemRecord $i): bool => $i->line->quantity->isPositive()));
            if ($items === []) {
                throw OperationsException::invalid('No hay cantidades recibidas para confirmar.');
            }
            // Orden fijo por producto: dos confirmaciones simultáneas no pueden bloquearse entre sí.
            usort($items, static fn (ReceiptItemRecord $a, ReceiptItemRecord $b): int => [$a->line->productId, $a->id] <=> [$b->line->productId, $b->id]);

            foreach ($items as $item) {
                $line = $item->line;
                $movementId = $this->movements->register(new MovementRequest(
                    kind: MovementKind::Purchase,
                    productId: $line->productId,
                    quantity: $line->quantity,
                    userId: $actorId,
                    idempotencyKey: $receipt->code.':L'.$item->id,
                    toLocationId: $line->locationId,
                    unitCost: $line->unitCost,
                    presentationId: $line->presentationId,
                    presentationQty: $line->presentationQty,
                    sourceType: 'RECEIPT',
                    sourceId: $receipt->id,
                    documentRef: mb_substr($receipt->docType.' '.$receipt->docNumber, 0, 60),
                ));
                $this->receipts->setItemMovement($item->id, $movementId);
            }

            $this->receipts->setReceiptStatus($receiptId, ReceiptStatus::Confirmed, $this->clock->now());
            $this->audit->record(new AuditEntry('receipt.confirmed', 'receipt', $receiptId, $actorId, ['status' => 'DRAFT'], [
                'status' => 'CONFIRMED', 'code' => $receipt->code, 'movements' => count($items), 'discrepancies' => $discrepancies,
            ]));

            return ['movements' => count($items), 'discrepancies' => $discrepancies];
        });
    }
}
