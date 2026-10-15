<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

interface AdjustmentRepository
{
    public function insertAdjustment(NewAdjustment $adjustment, string $code): int;

    /** Bloquea la solicitud (FOR UPDATE). */
    public function lockAdjustment(int $id): ?AdjustmentRecord;

    public function markApproved(int $id, int $reviewerId, int $movementId, ?string $reviewNotes): void;

    public function markRejected(int $id, int $reviewerId, string $reviewNotes): void;
}
