<?php

declare(strict_types=1);

namespace Tests\Support\Operations;

use App\Shared\Domain\Transaction;

/** Transacción simulada para cualquier conjunto de dobles con snapshot()/restore(). */
final class SnapshotTransaction implements Transaction
{
    /** @param list<object> $parts */
    public function __construct(private readonly array $parts) {}

    public function run(callable $callback): mixed
    {
        $states = array_map(static fn (object $p): mixed => $p->snapshot(), $this->parts);

        try {
            return $callback();
        } catch (\Throwable $e) {
            foreach ($this->parts as $i => $part) {
                $part->restore($states[$i]);
            }
            throw $e;
        }
    }
}
