<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use Illuminate\Support\Facades\DB;

final class DatabaseAuditTrail implements AuditTrail
{
    public function record(AuditEntry $entry): void
    {
        $request = app()->bound('request') ? request() : null;

        DB::table('audit_logs')->insert([
            'actor_id' => $entry->actorId,
            'action' => $entry->action,
            'entity_type' => $entry->entityType,
            'entity_id' => $entry->entityId,
            'old_values' => $entry->oldValues === null ? null : json_encode($entry->oldValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'new_values' => $entry->newValues === null ? null : json_encode($entry->newValues, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip' => $request?->ip(),
            'user_agent' => $request === null ? null : mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
