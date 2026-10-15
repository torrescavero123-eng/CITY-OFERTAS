<?php

declare(strict_types=1);

namespace App\Shared\Domain;

interface Transaction
{
    /**
     * Ejecuta el callback de forma atómica: o todo se confirma o nada.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(callable $callback): mixed;
}
