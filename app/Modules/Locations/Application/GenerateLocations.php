<?php

declare(strict_types=1);

namespace App\Modules\Locations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Locations\Domain\LocationBatch;
use App\Modules\Locations\Domain\LocationException;
use App\Modules\Locations\Domain\LocationRepository;
use App\Shared\Domain\Transaction;

/** Crea en lote solo las ubicaciones que faltan: se puede repetir sin duplicar. */
final class GenerateLocations
{
    public function __construct(
        private readonly LocationRepository $locations,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    /** @return array{created: int, skipped: int} */
    public function __invoke(LocationBatch $batch): array
    {
        return $this->transaction->run(function () use ($batch): array {
            $warehouse = $this->locations->warehouse($batch->warehouseId);
            if ($warehouse === null || ! $warehouse->isActive) {
                throw LocationException::warehouseNotFound($batch->warehouseId);
            }

            $all = $batch->expand($warehouse->code);
            $existing = array_flip($this->locations->existingCodes(array_map(static fn ($l): string => $l->code, $all)));
            $missing = array_values(array_filter($all, static fn ($l): bool => ! isset($existing[$l->code])));

            if ($missing !== []) {
                $this->locations->insertMany($batch->warehouseId, $missing);
                $this->audit->record(new AuditEntry('location.generated', 'warehouse', $batch->warehouseId, $batch->actorId, null, [
                    'zone' => 'Z'.$batch->zone, 'created' => count($missing), 'first' => $missing[0]->code, 'last' => $missing[count($missing) - 1]->code,
                ]));
            }

            return ['created' => count($missing), 'skipped' => count($all) - count($missing)];
        });
    }
}
