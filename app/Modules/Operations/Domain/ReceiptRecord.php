<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class ReceiptRecord
{
    /** @param list<ReceiptItemRecord> $items */
    public function __construct(
        public int $id,
        public string $code,
        public ReceiptStatus $status,
        public int $supplierId,
        public int $warehouseId,
        public string $docType,
        public string $docNumber,
        public array $items,
    ) {}
}
