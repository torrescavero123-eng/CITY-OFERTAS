<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Inventory\Domain\Transaction;

/** Emula una transacción: si el callback falla, el ledger en memoria vuelve a su estado previo. */
final class RollbackTransaction implements Transaction
{
    public function __construct(private readonly InMemoryStockLedger $ledger) {}

    public function run(callable $callback): mixed
    {
        $snapshot = $this->ledger->snapshot();

        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->ledger->restore($snapshot);
            throw $e;
        }
    }
}
