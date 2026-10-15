<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class ReceiptItemRecord
{
    public function __construct(
        public int $id,
        public ReceiptLine $line,
        public ?int $movementId = null,
    ) {}
}
