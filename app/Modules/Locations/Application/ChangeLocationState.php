<?php

declare(strict_types=1);

namespace App\Modules\Locations\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Locations\Domain\LocationException;
use App\Modules\Locations\Domain\LocationRecord;
use App\Modules\Locations\Domain\LocationRepository;
use App\Shared\Domain\Transaction;

/**
 * Congelar / descongelar (durante un conteo) y activar / desactivar ubicaciones. Todas idempotentes.
 */
final class ChangeLocationState
{
    public function __construct(
        private readonly LocationRepository $locations,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function freeze(int $locationId, ?int $actorId = null): void
    {
        $this->change($locationId, $actorId, 'location.frozen', static function ($loc, LocationRepository $repo): ?array {
            if ($loc->isFrozen) {
                return null;
            }
            $repo->setFrozen($loc->id, true);

            return [['is_frozen' => false], ['is_frozen' => true]];
        });
    }

    public function unfreeze(int $locationId, ?int $actorId = null): void
    {
        $this->change($locationId, $actorId, 'location.unfrozen', static function ($loc, LocationRepository $repo): ?array {
            if (! $loc->isFrozen) {
                return null;
            }
            $repo->setFrozen($loc->id, false);

            return [['is_frozen' => true], ['is_frozen' => false]];
        });
    }

    /**
     * Congela varias ubicaciones a la vez (inicio de un conteo). Si alguna ya está congelada o desactivada,
     * no se congela ninguna. Una sola entrada de auditoría.
     *
     * @param  list<int>  $locationIds
     */
    public function freezeMany(array $locationIds, ?int $actorId = null, string $reference = ''): int
    {
        return $this->transaction->run(function () use ($locationIds, $actorId, $reference): int {
            $ids = array_values(array_unique($locationIds));
            $records = $this->locations->lockMany($ids);

            if (count($records) !== count($ids)) {
                throw LocationException::notFound(array_values(array_diff($ids, array_map(static fn ($r): int => $r->id, $records)))[0]);
            }
            foreach ($records as $r) {
                if (! $r->isActive) {
                    throw LocationException::invalid("La ubicación {$r->code} está desactivada.");
                }
                if ($r->isFrozen) {
                    throw LocationException::isFrozen($r->code);
                }
            }

            $this->locations->setFrozenMany($ids, true);
            $this->audit->record(new AuditEntry('location.frozen_bulk', 'location', null, $actorId, ['is_frozen' => false], ['is_frozen' => true, 'count' => count($ids), 'reference' => $reference]));

            return count($ids);
        });
    }

    /** @param list<int> $locationIds */
    public function unfreezeMany(array $locationIds, ?int $actorId = null, string $reference = ''): int
    {
        return $this->transaction->run(function () use ($locationIds, $actorId, $reference): int {
            $ids = array_values(array_unique($locationIds));
            $frozen = array_values(array_map(
                static fn ($r): int => $r->id,
                array_filter($this->locations->lockMany($ids), static fn ($r): bool => $r->isFrozen),
            ));

            if ($frozen !== []) {
                $this->locations->setFrozenMany($frozen, false);
                $this->audit->record(new AuditEntry('location.unfrozen_bulk', 'location', null, $actorId, ['is_frozen' => true], ['is_frozen' => false, 'count' => count($frozen), 'reference' => $reference]));
            }

            return count($frozen);
        });
    }

    public function deactivate(int $locationId, ?int $actorId = null): void
    {
        $this->change($locationId, $actorId, 'location.deactivated', function ($loc, LocationRepository $repo): ?array {
            if (! $loc->isActive) {
                return null;
            }
            if ($loc->isStaging) {
                throw LocationException::isStaging($loc->code);
            }
            if ($loc->isFrozen) {
                throw LocationException::isFrozen($loc->code);
            }
            $stock = $repo->stockAt($loc->id);
            if (bccomp($stock, '0', 3) > 0) {
                throw LocationException::hasStock($loc->code, $stock);
            }
            $repo->setActive($loc->id, false);

            return [['is_active' => true], ['is_active' => false]];
        });
    }

    public function activate(int $locationId, ?int $actorId = null): void
    {
        $this->change($locationId, $actorId, 'location.activated', static function ($loc, LocationRepository $repo): ?array {
            if ($loc->isActive) {
                return null;
            }
            $repo->setActive($loc->id, true);

            return [['is_active' => false], ['is_active' => true]];
        });
    }

    /** @param callable(LocationRecord, LocationRepository): (array{0: array<string, mixed>, 1: array<string, mixed>}|null) $apply */
    private function change(int $locationId, ?int $actorId, string $action, callable $apply): void
    {
        $this->transaction->run(function () use ($locationId, $actorId, $action, $apply): void {
            $location = $this->locations->lock($locationId) ?? throw LocationException::notFound($locationId);
            $result = $apply($location, $this->locations);

            if ($result !== null) {
                $this->audit->record(new AuditEntry($action, 'location', $locationId, $actorId, $result[0], $result[1]));
            }
        });
    }
}
