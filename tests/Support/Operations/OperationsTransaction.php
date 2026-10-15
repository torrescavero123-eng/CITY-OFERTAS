<?php

declare(strict_types=1);

namespace Tests\Support\Operations;

use App\Shared\Domain\Transaction;

/** Si el callback falla, tanto los documentos como los movimientos vuelven a su estado previo. */
final class OperationsTransaction implements Transaction
{
    public function __construct(private readonly InMemoryOperationsStore $store, private readonly FakeStockMovements $movements) {}

    public function run(callable $callback): mixed
    {
        $a = $this->store->snapshot();
        $b = $this->movements->snapshot();

        try {
            return $callback();
        } catch (\Throwable $e) {
            $this->store->restore($a);
            $this->movements->restore($b);
            throw $e;
        }
    }
}
