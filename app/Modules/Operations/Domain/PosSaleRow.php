<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class PosSaleRow
{
    public function __construct(public int $line, public string $code, public string $quantity) {}
}
