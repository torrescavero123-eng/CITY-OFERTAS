<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use App\Modules\Catalog\Domain\SkuSequence;

final class InMemorySkuSequence implements SkuSequence
{
    /** @var array<string, int> */
    private array $counters = [];

    public function next(string $categoryRootCode): string
    {
        $this->counters[$categoryRootCode] = ($this->counters[$categoryRootCode] ?? 0) + 1;

        return sprintf('%s-%06d', $categoryRootCode, $this->counters[$categoryRootCode]);
    }
}
