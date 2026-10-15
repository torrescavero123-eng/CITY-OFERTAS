<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

final readonly class CategoryInfo
{
    public function __construct(public int $id, public string $rootCode, public bool $isActive) {}
}
