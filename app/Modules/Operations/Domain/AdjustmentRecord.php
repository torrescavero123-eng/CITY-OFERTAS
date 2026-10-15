<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class AdjustmentRecord
{
    public function __construct(
        public int $id,
        public string $code,
        public AdjustmentStatus $status,
        public NewAdjustment $request,
        public ?int $movementId = null,
    ) {}
}
