<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Domain\Transaction;
use Illuminate\Support\Facades\DB;

final class LaravelTransaction implements Transaction
{
    private const ATTEMPTS = 3; // reintento ante deadlock

    public function run(callable $callback): mixed
    {
        return DB::transaction(static fn (): mixed => $callback(), self::ATTEMPTS);
    }
}
