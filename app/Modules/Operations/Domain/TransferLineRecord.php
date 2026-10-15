<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class TransferLineRecord
{
    public function __construct(public int $id, public TransferLine $line, public ?int $movementId = null) {}
}
