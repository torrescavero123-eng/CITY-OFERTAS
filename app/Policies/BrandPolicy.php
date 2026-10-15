<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

/** Lectura: catalog.view. Escritura: catalog.write. Nunca se borra: se da de baja (los datos históricos se conservan). */
final class BrandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.view');
    }

    public function view(User $user, Brand $record): bool
    {
        return $user->can('catalog.view');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.write');
    }

    public function update(User $user, Brand $record): bool
    {
        return $user->can('catalog.write');
    }

    public function delete(User $user, Brand $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
