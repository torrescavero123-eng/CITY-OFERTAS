<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

interface TransferRepository
{
    public function insertTransfer(NewTransfer $transfer, string $code): int;

    public function lockTransfer(int $id): ?TransferRecord;

    public function setLineMovement(int $lineId, int $movementId): void;

    public function setTransferStatus(int $id, TransferStatus $status, ?\DateTimeImmutable $completedAt = null): void;
}
