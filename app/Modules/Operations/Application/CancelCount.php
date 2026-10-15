<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\CountLocations;
use App\Modules\Operations\Domain\CountRepository;
use App\Modules\Operations\Domain\CountStatus;
use App\Modules\Operations\Domain\OperationsException;
use App\Shared\Domain\Transaction;

/** Anula un conteo en curso o en revisión: descongela y no ajusta nada. Un conteo cerrado no se anula. */
final class CancelCount
{
    public function __construct(
        private readonly CountRepository $counts,
        private readonly CountLocations $locations,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $countId, int $actorId): void
    {
        $this->transaction->run(function () use ($countId, $actorId): void {
            $count = $this->counts->lockCount($countId) ?? throw OperationsException::notFound('El conteo', $countId);

            if ($count->status === CountStatus::Cancelled) {
                return;
            }
            if ($count->status === CountStatus::Closed) {
                throw OperationsException::wrongState('Un conteo cerrado no se anula: sus ajustes ya están en el ledger.');
            }

            $this->locations->unfreeze($count->locationIds, $actorId, $count->code);
            $this->counts->setCountStatus($countId, CountStatus::Cancelled);
            $this->audit->record(new AuditEntry('count.cancelled', 'count', $countId, $actorId, ['status' => $count->status->value], ['status' => 'CANCELLED']));
        });
    }
}
