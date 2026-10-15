<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class ImportError
{
    public function __construct(public int $line, public string $message) {}
}
