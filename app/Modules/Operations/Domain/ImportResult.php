<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

final readonly class ImportResult
{
    /** @param list<ImportError> $errors */
    public function __construct(
        public int $importId,
        public bool $committed,
        public int $totalRows,
        public int $okRows,
        public array $errors,
        /** Total de unidades descontadas (o que se descontarían en simulación). */
        public string $units,
    ) {}

    /** Reporte descargable de errores: línea;mensaje (con BOM para que Excel lo abra bien). */
    public function errorReportCsv(): string
    {
        $out = "\xEF\xBB\xBFlinea;error\r\n";
        foreach ($this->errors as $e) {
            $out .= $e->line.';"'.str_replace('"', '""', $e->message)."\"\r\n";
        }

        return $out;
    }
}
