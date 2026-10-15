<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

interface DocumentNumbers
{
    /** Siguiente número correlativo atómico del año, p. ej. REC-2026-000123. */
    public function next(string $prefix): string;
}
