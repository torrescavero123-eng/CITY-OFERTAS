<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseReceipt;
use App\Models\User;

/** Ver: inventory.view. Crear: inventory.receive. Los cambios de estado (confirmar, anular, aprobar) se controlan en cada acción. */
final class PurchaseReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, PurchaseReceipt $record): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.receive');
    }

    public function update(User $user, PurchaseReceipt $record): bool
    {
        return false;
    }

    public function delete(User $user, PurchaseReceipt $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
