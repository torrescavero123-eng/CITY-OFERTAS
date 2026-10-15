<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\MovementRequest;
use App\Modules\Operations\Domain\NewAdjustment;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\OperationsLookup;
use App\Modules\Operations\Domain\StockMovements;
use App\Shared\Domain\Transaction;

/**
 * Registro directo (sin segunda persona) para supervisores, solo si el valor del ajuste no supera el límite
 * configurado (adjustment.auto_approve_limit_pen; 0 = deshabilitado). Queda auditado como "directo".
 * El permiso (inventory.adjust.approve) lo exige la capa de presentación.
 */
final class RecordDirectAdjustment
{
    public function __construct(
        private readonly OperationsLookup $lookup,
        private readonly StockMovements $movements,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    /** @return int id del movimiento */
    public function __invoke(NewAdjustment $adjustment, string $idempotencyKey): int
    {
        return $this->transaction->run(function () use ($adjustment, $idempotencyKey): int {
            RequestAdjustment::assertValid($this->lookup, $adjustment);

            $value = bcmul($adjustment->quantity->value, (string) $this->lookup->averageCost($adjustment->productId), 4);
            $limit = $this->lookup->directAdjustmentLimit();
            if (bccomp($limit, '0', 2) === 0 || bccomp($value, $limit, 4) > 0) {
                throw OperationsException::exceedsDirectLimit(bcadd($value, '0', 2), $limit);
            }

            $positive = $adjustment->kind === AdjustmentKind::Positive;
            $movementId = $this->movements->register(new MovementRequest(
                kind: $adjustment->kind->movementKind(),
                productId: $adjustment->productId,
                quantity: $adjustment->quantity,
                userId: $adjustment->requestedBy,
                idempotencyKey: $idempotencyKey,
                fromLocationId: $positive ? null : $adjustment->locationId,
                toLocationId: $positive ? $adjustment->locationId : null,
                reasonId: $adjustment->reasonId,
                sourceType: 'ADJUSTMENT',
                documentRef: 'DIRECTO',
                notes: $adjustment->notes,
                approvedBy: $adjustment->requestedBy,
            ));

            $this->audit->record(new AuditEntry('adjustment.direct', 'adjustment', $movementId, $adjustment->requestedBy, null, [
                'kind' => $adjustment->kind->value, 'product_id' => $adjustment->productId, 'quantity' => $adjustment->quantity->value,
                'value_pen' => bcadd($value, '0', 2), 'movement_id' => $movementId,
            ]));

            return $movementId;
        });
    }
}
