<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

final readonly class PresentationInput
{
    public function __construct(
        public int $unitId,
        public Factor $factor,
        public bool $isPurchaseDefault = false,
    ) {}
}
