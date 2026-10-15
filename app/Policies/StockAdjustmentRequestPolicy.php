<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockAdjustmentRequest;
use App\Models\User;

/** Ver: inventory.view. Crear: inventory.adjust.request. Los cambios de estado (confirmar, anular, aprobar) se controlan en cada acción. */
final class StockAdjustmentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, StockAdjustmentRequest $record): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.adjust.request');
    }

    public function update(User $user, StockAdjustmentRequest $record): bool
    {
        return false;
    }

    public function delete(User $user, StockAdjustmentRequest $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
