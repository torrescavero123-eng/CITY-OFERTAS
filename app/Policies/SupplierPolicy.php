<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

/** Compras y administración gestionan proveedores (suppliers.manage). Nunca se borran: se desactivan. */
final class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('suppliers.manage');
    }

    public function view(User $user, Supplier $record): bool
    {
        return $user->can('suppliers.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.manage');
    }

    public function update(User $user, Supplier $record): bool
    {
        return $user->can('suppliers.manage');
    }

    public function delete(User $user, Supplier $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
