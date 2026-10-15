<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use Illuminate\Database\Eloquent\Model;

/** Audita altas y cambios de modelos simples (categorías, marcas) con antes/después. */
trait RecordsAudit
{
    abstract protected static function auditEntity(): string;

    protected static function bootRecordsAudit(): void
    {
        static::created(static function (Model $model): void {
            app(AuditTrail::class)->record(new AuditEntry(
                static::auditEntity().'.created', static::auditEntity(), (int) $model->getKey(),
                auth()->id() === null ? null : (int) auth()->id(), null, $model->getAttributes(),
            ));
        });

        static::updated(static function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            app(AuditTrail::class)->record(new AuditEntry(
                static::auditEntity().'.updated', static::auditEntity(), (int) $model->getKey(),
                auth()->id() === null ? null : (int) auth()->id(), array_intersect_key($model->getOriginal(), $changes), $changes,
            ));
        });
    }
}
