<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Domain\Transaction;

final class PassthroughTransaction implements Transaction
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}
