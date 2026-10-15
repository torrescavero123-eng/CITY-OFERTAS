<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

/** Registro de auditoría append-only. Nunca debe recibir contraseñas ni secretos. */
interface AuditTrail
{
    public function record(AuditEntry $entry): void;
}
