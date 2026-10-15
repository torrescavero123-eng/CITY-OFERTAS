<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TransferOrder;
use App\Models\User;

/** Ver: inventory.view. Crear: inventory.transfer. Los cambios de estado (confirmar, anular, aprobar) se controlan en cada acción. */
final class TransferOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, TransferOrder $record): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.transfer');
    }

    public function update(User $user, TransferOrder $record): bool
    {
        return false;
    }

    public function delete(User $user, TransferOrder $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
