<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class CountRecord
{
    /** @param list<int> $locationIds ubicaciones congeladas por este conteo */
    public function __construct(
        public int $id,
        public string $code,
        public int $warehouseId,
        public CountStatus $status,
        public int $openedBy,
        public array $locationIds,
    ) {}
}
