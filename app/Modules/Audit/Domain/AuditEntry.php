<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

final readonly class AuditEntry
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __construct(
        public string $action,
        public string $entityType,
        public ?int $entityId = null,
        public ?int $actorId = null,
        public ?array $oldValues = null,
        public ?array $newValues = null,
    ) {}
}
