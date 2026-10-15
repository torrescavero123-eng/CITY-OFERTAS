<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Domain\CountRepository;
use App\Modules\Operations\Domain\CountStatus;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\OperationsLookup;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Transaction;

/**
 * Registra lo contado de un producto en una ubicación. Contar de nuevo la misma línea reemplaza el valor.
 * En REVIEW solo se pueden recontar las líneas marcadas para reconteo.
 */
final class RecordCount
{
    public function __construct(
        private readonly CountRepository $counts,
        private readonly OperationsLookup $lookup,
        private readonly Transaction $transaction,
        private readonly Clock $clock,
    ) {}

    public function __invoke(int $countId, int $productId, int $locationId, Quantity $counted, int $userId): void
    {
        $this->transaction->run(function () use ($countId, $productId, $locationId, $counted, $userId): void {
            $count = $this->counts->lockCount($countId) ?? throw OperationsException::notFound('El conteo', $countId);

            if (! in_array($locationId, $count->locationIds, true)) {
                throw OperationsException::invalid('Esa ubicación no pertenece a este conteo.');
            }

            $line = $this->counts->findLine($countId, $productId, $locationId);

            if ($count->status === CountStatus::Counting) {
                if ($line === null && $this->lookup->averageCost($productId) === null) {
                    throw OperationsException::notFound('El producto', $productId); // hallazgo de un producto que no existe
                }
                $this->counts->saveCounted($countId, $productId, $locationId, $counted, $userId, $this->clock->now(), false);

                return;
            }

            if ($count->status === CountStatus::Review) {
                if ($line === null || ! $line->recountRequested) {
                    throw OperationsException::wrongState('En revisión solo se pueden recontar las líneas marcadas para reconteo.');
                }
                $this->counts->saveCounted($countId, $productId, $locationId, $counted, $userId, $this->clock->now(), false);

                return;
            }

            throw OperationsException::wrongState('El conteo ya no admite cambios.');
        });
    }
}
