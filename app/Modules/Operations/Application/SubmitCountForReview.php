<?php

declare(strict_types=1);

namespace App\Modules\Operations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Operations\Domain\CountLineRecord;
use App\Modules\Operations\Domain\CountRepository;
use App\Modules\Operations\Domain\CountStatus;
use App\Modules\Operations\Domain\OperationsException;
use App\Shared\Domain\Transaction;

/** Pasa a revisión. Toda línea debe estar contada (no contar NO significa cero). Las diferencias piden un reconteo. */
final class SubmitCountForReview
{
    public function __construct(
        private readonly CountRepository $counts,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    /** @return array{lines: int, withDifference: int} */
    public function __invoke(int $countId, int $actorId): array
    {
        return $this->transaction->run(function () use ($countId, $actorId): array {
            $count = $this->counts->lockCount($countId) ?? throw OperationsException::notFound('El conteo', $countId);

            if ($count->status !== CountStatus::Counting) {
                throw OperationsException::wrongState('Solo un conteo en curso se puede enviar a revisión.');
            }

            $lines = $this->counts->countLines($countId);
            $pending = count(array_filter($lines, static fn (CountLineRecord $l): bool => $l->counted === null));
            if ($pending > 0) {
                throw OperationsException::wrongState("Faltan {$pending} líneas por contar. Si no hay producto, cuéntelo como 0.");
            }

            $different = array_values(array_filter($lines, static fn (CountLineRecord $l): bool => $l->hasDifference()));
            $this->counts->flagRecounts($countId, array_map(static fn (CountLineRecord $l): int => $l->id, $different));
            $this->counts->setCountStatus($countId, CountStatus::Review);

            $this->audit->record(new AuditEntry('count.submitted', 'count', $countId, $actorId, ['status' => 'COUNTING'], [
                'status' => 'REVIEW', 'lines' => count($lines), 'with_difference' => count($different),
            ]));

            return ['lines' => count($lines), 'withDifference' => count($different)];
        });
    }
}
