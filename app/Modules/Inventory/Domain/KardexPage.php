<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

final readonly class KardexPage
{
    /** @param list<KardexRow> $rows */
    public function __construct(public string $openingBalance, public array $rows, public string $closingBalance) {}
}
