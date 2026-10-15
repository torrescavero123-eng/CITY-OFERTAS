<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

use App\Modules\Inventory\Domain\Quantity;

interface CountRepository
{
    /** Crea el conteo (COUNTING) con su alcance y las líneas esperadas. */
    public function insertCount(NewCount $count, string $code, CountScope $scope, \DateTimeImmutable $snapshotAt): int;

    /** Bloquea el conteo (FOR UPDATE). */
    public function lockCount(int $id): ?CountRecord;

    /** @return list<CountLineRecord> */
    public function countLines(int $countId): array;

    public function findLine(int $countId, int $productId, int $locationId): ?CountLineRecord;

    /** Crea (esperado 0, hallazgo) o actualiza la línea con lo contado. */
    public function saveCounted(int $countId, int $productId, int $locationId, Quantity $counted, int $userId, \DateTimeImmutable $at, bool $recountRequested): void;

    /** @param list<int> $lineIds */
    public function flagRecounts(int $countId, array $lineIds): void;

    public function setLineAdjustment(int $lineId, int $adjustmentId): void;

    public function setCountStatus(int $id, CountStatus $status, ?int $closedBy = null, ?\DateTimeImmutable $closedAt = null): void;
}
