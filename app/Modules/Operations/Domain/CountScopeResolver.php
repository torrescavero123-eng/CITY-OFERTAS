<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

interface CountScopeResolver
{
    /** Ubicaciones a congelar y stock esperado para el alcance pedido. */
    public function resolve(int $warehouseId, CountScopeType $type, ?string $value): CountScope;
}
