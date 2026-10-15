<?php

declare(strict_types=1);

namespace Tests\Support\Operations;

use App\Modules\Operations\Domain\MovementRequest;
use App\Modules\Operations\Domain\OperationsException;
use App\Modules\Operations\Domain\StockMovements;

/** Doble del puerto hacia Inventory: idempotente por clave y capaz de rechazar productos concretos. */
final class FakeStockMovements implements StockMovements
{
    /** @var array<string, int> clave => id */
    public array $byKey = [];

    /** @var list<MovementRequest> */
    public array $requests = [];

    /** @var list<int> productos cuyo movimiento será rechazado (stock insuficiente) */
    public array $rejectProducts = [];

    public function register(MovementRequest $request): int
    {
        if (isset($this->byKey[$request->idempotencyKey])) {
            return $this->byKey[$request->idempotencyKey];
        }
        if (in_array($request->productId, $this->rejectProducts, true)) {
            throw OperationsException::stockRejected("Stock insuficiente para el producto {$request->productId}.", 'INSUFFICIENT_STOCK');
        }

        $id = count($this->byKey) + 1;
        $this->byKey[$request->idempotencyKey] = $id;
        $this->requests[] = $request;

        return $id;
    }

    /** @return array{0: array<string, int>, 1: list<MovementRequest>} */
    public function snapshot(): array
    {
        return [$this->byKey, $this->requests];
    }

    /** @param array{0: array<string, int>, 1: list<MovementRequest>} $state */
    public function restore(array $state): void
    {
        [$this->byKey, $this->requests] = $state;
    }
}
