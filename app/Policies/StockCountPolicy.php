<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockCount;
use App\Models\User;

/** Ver: inventory.view. Crear/iniciar: counts.close. Los cambios de estado se controlan en cada acción. */
final class StockCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, StockCount $record): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('counts.close');
    }

    public function update(User $user, StockCount $record): bool
    {
        return false;
    }

    public function delete(User $user, StockCount $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
