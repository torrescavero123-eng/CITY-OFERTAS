<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure;

use App\Modules\Inventory\Domain\Transaction;
use Illuminate\Support\Facades\DB;

final class LaravelTransaction implements Transaction
{
    /** Reintentos ante deadlock: el caso de uso es idempotente, por lo que repetirlo es seguro. */
    private const ATTEMPTS = 3;

    public function run(callable $callback): mixed
    {
        return DB::transaction(static fn (): mixed => $callback(), self::ATTEMPTS);
    }
}
