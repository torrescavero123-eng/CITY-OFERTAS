<?php

declare(strict_types=1);

namespace Tests\Support\Operations;

use App\Modules\Operations\Domain\ImportJobs;
use App\Modules\Operations\Domain\SalesTarget;
use App\Modules\Operations\Domain\SalesTargets;

/** Códigos de la caja => producto y ubicación del piso; y registro de importaciones. */
final class FakePosInfra implements ImportJobs, SalesTargets
{
    /** @var array<string, SalesTarget> */
    public array $targets = [];

    /** @var list<array{kind: string, file: string, sha: string, commit: bool, total: int, ok: int, errors: int, user: int}> */
    public array $jobs = [];

    public function resolve(string $code): ?SalesTarget
    {
        return $this->targets[mb_strtoupper($code)] ?? null;
    }

    public function committedBefore(string $kind, string $sha256): bool
    {
        foreach ($this->jobs as $j) {
            if ($j['kind'] === $kind && $j['sha'] === $sha256 && $j['commit']) {
                return true;
            }
        }

        return false;
    }

    public function record(string $kind, string $fileName, string $sha256, bool $commit, int $totalRows, int $okRows, int $errorRows, int $userId): int
    {
        $this->jobs[] = ['kind' => $kind, 'file' => $fileName, 'sha' => $sha256, 'commit' => $commit, 'total' => $totalRows, 'ok' => $okRows, 'errors' => $errorRows, 'user' => $userId];

        return count($this->jobs);
    }

    /** @return array<int, mixed> */
    public function snapshot(): array
    {
        return [$this->jobs];
    }

    /** @param array<int, mixed> $state */
    public function restore(array $state): void
    {
        [$this->jobs] = $state;
    }
}
