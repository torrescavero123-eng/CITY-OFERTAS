<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\Operations\Domain\ImportJobs;
use App\Modules\Operations\Domain\OperationsException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class DatabaseImportJobs implements ImportJobs
{
    public function committedBefore(string $kind, string $sha256): bool
    {
        return DB::table('import_jobs')->where('kind', $kind)->where('file_sha256', $sha256)
            ->where('mode', 'COMMIT')->where('status', '<>', 'FAILED')->exists();
    }

    public function record(string $kind, string $fileName, string $sha256, bool $commit, int $totalRows, int $okRows, int $errorRows, int $userId): int
    {
        try {
            return (int) DB::table('import_jobs')->insertGetId([
                'kind' => $kind,
                'file_path' => $fileName,
                'file_sha256' => $sha256,
                'mode' => $commit ? 'COMMIT' : 'DRY_RUN',
                'status' => 'DONE',
                'total_rows' => $totalRows,
                'ok_rows' => $okRows,
                'error_rows' => $errorRows,
                'created_by' => $userId,
                'finished_at' => now(),
            ], 'id');
        } catch (UniqueConstraintViolationException) {
            // Dos usuarios subieron el mismo archivo a la vez: la BD deja pasar solo uno.
            throw OperationsException::wrongState('Este archivo ya se importó antes.');
        }
    }
}
