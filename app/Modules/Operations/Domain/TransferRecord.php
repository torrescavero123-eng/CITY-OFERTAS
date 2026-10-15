<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class TransferRecord
{
    /** @param list<TransferLineRecord> $lines */
    public function __construct(public int $id, public string $code, public TransferStatus $status, public int $requestedBy, public array $lines) {}
}
