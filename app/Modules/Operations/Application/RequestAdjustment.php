<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\AdjustmentRepository;
use App\Modules\Operations\Domain\DocumentNumbers;
use App\Modules\Operations\Domain\NewAdjustment;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\OperationsLookup;
use App\Shared\Domain\Transaction;

/** Un almacenero SOLICITA el ajuste o la merma; otra persona lo aprueba. No toca el stock todavía. */
final class RequestAdjustment
{
    public function __construct(
        private readonly AdjustmentRepository $adjustments,
        private readonly OperationsLookup $lookup,
        private readonly DocumentNumbers $numbers,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    /** @return array{id: int, code: string} */
    public function __invoke(NewAdjustment $adjustment): array
    {
        return $this->transaction->run(function () use ($adjustment): array {
            self::assertValid($this->lookup, $adjustment);

            $code = $this->numbers->next('AJU');
            $id = $this->adjustments->insertAdjustment($adjustment, $code);
            $this->audit->record(new AuditEntry('adjustment.requested', 'adjustment', $id, $adjustment->requestedBy, null, [
                'code' => $code, 'kind' => $adjustment->kind->value, 'product_id' => $adjustment->productId,
                'location_id' => $adjustment->locationId, 'quantity' => $adjustment->quantity->value,
            ]));

            return ['id' => $id, 'code' => $code];
        });
    }

    /** Validaciones comunes a la solicitud y al registro directo. */
    public static function assertValid(OperationsLookup $lookup, NewAdjustment $adjustment): void
    {
        $reason = $lookup->reason($adjustment->reasonId);
        if ($reason === null || ! $reason->isActive) {
            throw OperationsException::invalid('El motivo no existe o está desactivado.');
        }
        if ($reason->movementType !== $adjustment->kind->reasonMovementType()) {
            throw OperationsException::invalid('El motivo elegido no corresponde a este tipo de ajuste.');
        }
        if ($lookup->locationWarehouseId($adjustment->locationId) === null) {
            throw OperationsException::notFound('La ubicación', $adjustment->locationId);
        }
        if ($lookup->averageCost($adjustment->productId) === null) {
            throw OperationsException::notFound('El producto', $adjustment->productId);
        }
    }
}
