<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

/** Puerto hacia el módulo Inventory (único que escribe stock). */
interface StockMovements
{
    /**
     * @return int id del movimiento (el mismo si la clave ya se había registrado)
     *
     * @throws OperationsException si el almacén lo rechaza
     */
    public function register(MovementRequest $request): int;
}
