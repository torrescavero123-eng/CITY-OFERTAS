<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

final readonly class RecordedMovement
{
    public function __construct(public int $id, public string $uuid) {}
}
