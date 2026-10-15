<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

interface ImportJobs
{
    /** ¿Ya se confirmó un archivo con este mismo contenido? */
    public function committedBefore(string $kind, string $sha256): bool;

    public function record(string $kind, string $fileName, string $sha256, bool $commit, int $totalRows, int $okRows, int $errorRows, int $userId): int;
}
