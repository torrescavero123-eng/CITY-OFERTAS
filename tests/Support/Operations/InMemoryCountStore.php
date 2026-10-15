<?php

declare(strict_types=1);

namespace Tests\Support\Operations;

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Domain\CountLineRecord;
use App\Modules\Operations\Domain\CountLocations;
use App\Modules\Operations\Domain\CountRecord;
use App\Modules\Operations\Domain\CountRepository;
use App\Modules\Operations\Domain\CountScope;
use App\Modules\Operations\Domain\CountScopeResolver;
use App\Modules\Operations\Domain\CountScopeType;
use App\Modules\Operations\Domain\CountStatus;
use App\Modules\Operations\Domain\NewCount;
use App\Modules\Operations\Domain\OperationsException;

final class InMemoryCountStore implements CountLocations, CountRepository, CountScopeResolver
{
    /** Alcance que devolverá el resolvedor. */
    public CountScope $scope;

    /** @var array<int, array{code: string, warehouseId: int, status: CountStatus, openedBy: int, locationIds: list<int>, lines: array<int, CountLineRecord>}> */
    public array $counts = [];

    /** @var list<int> */
    public array $frozen = [];

    private int $nextLineId = 1;

    public function __construct()
    {
        $this->scope = new CountScope([], []);
    }

    // ---- CountScopeResolver ----
    public function resolve(int $warehouseId, CountScopeType $type, ?string $value): CountScope
    {
        return $this->scope;
    }

    // ---- CountLocations ----
    public function freeze(array $locationIds, int $actorId, string $reference): void
    {
        foreach ($locationIds as $id) {
            if (in_array($id, $this->frozen, true)) {
                throw OperationsException::invalid("La ubicación {$id} ya está congelada por otra operación.");
            }
        }
        $this->frozen = [...$this->frozen, ...$locationIds];
    }

    public function unfreeze(array $locationIds, int $actorId, string $reference): void
    {
        $this->frozen = array_values(array_diff($this->frozen, $locationIds));
    }

    // ---- CountRepository ----
    public function insertCount(NewCount $count, string $code, CountScope $scope, \DateTimeImmutable $snapshotAt): int
    {
        $id = count($this->counts) + 1;
        $lines = [];
        foreach ($scope->balances as $b) {
            $lines[$this->nextLineId] = new CountLineRecord($this->nextLineId, $b->productId, $b->locationId, $b->quantity);
            $this->nextLineId++;
        }
        $this->counts[$id] = [
            'code' => $code, 'warehouseId' => $count->warehouseId, 'status' => CountStatus::Counting,
            'openedBy' => $count->openedBy, 'locationIds' => $scope->locationIds, 'lines' => $lines,
        ];

        return $id;
    }

    public function lockCount(int $id): ?CountRecord
    {
        $c = $this->counts[$id] ?? null;

        return $c === null ? null : new CountRecord($id, $c['code'], $c['warehouseId'], $c['status'], $c['openedBy'], $c['locationIds']);
    }

    public function countLines(int $countId): array
    {
        return array_values($this->counts[$countId]['lines']);
    }

    public function findLine(int $countId, int $productId, int $locationId): ?CountLineRecord
    {
        foreach ($this->counts[$countId]['lines'] as $line) {
            if ($line->productId === $productId && $line->locationId === $locationId) {
                return $line;
            }
        }

        return null;
    }

    public function saveCounted(int $countId, int $productId, int $locationId, Quantity $counted, int $userId, \DateTimeImmutable $at, bool $recountRequested): void
    {
        $existing = $this->findLine($countId, $productId, $locationId);
        $id = $existing->id ?? $this->nextLineId++;
        $this->counts[$countId]['lines'][$id] = new CountLineRecord(
            $id, $productId, $locationId, $existing->expected ?? Quantity::zero(), $counted, $recountRequested, $userId, $existing?->adjustmentId,
        );
    }

    public function flagRecounts(int $countId, array $lineIds): void
    {
        foreach ($lineIds as $lineId) {
            $l = $this->counts[$countId]['lines'][$lineId];
            $this->counts[$countId]['lines'][$lineId] = new CountLineRecord($l->id, $l->productId, $l->locationId, $l->expected, $l->counted, true, $l->countedBy, $l->adjustmentId);
        }
    }

    public function setLineAdjustment(int $lineId, int $adjustmentId): void
    {
        foreach ($this->counts as $cid => $c) {
            if (isset($c['lines'][$lineId])) {
                $l = $c['lines'][$lineId];
                $this->counts[$cid]['lines'][$lineId] = new CountLineRecord($l->id, $l->productId, $l->locationId, $l->expected, $l->counted, $l->recountRequested, $l->countedBy, $adjustmentId);
            }
        }
    }

    public function setCountStatus(int $id, CountStatus $status, ?int $closedBy = null, ?\DateTimeImmutable $closedAt = null): void
    {
        $this->counts[$id]['status'] = $status;
    }

    /** @return array<int, mixed> */
    public function snapshot(): array
    {
        return [$this->counts, $this->frozen, $this->nextLineId];
    }

    /** @param array<int, mixed> $state */
    public function restore(array $state): void
    {
        [$this->counts, $this->frozen, $this->nextLineId] = $state;
    }
}
