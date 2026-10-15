<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ImportJob;
use App\Models\User;

/** Ver: pos.import. Crear/iniciar: pos.import. Los cambios de estado se controlan en cada acción. */
final class ImportJobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pos.import');
    }

    public function view(User $user, ImportJob $record): bool
    {
        return $user->can('pos.import');
    }

    public function create(User $user): bool
    {
        return $user->can('pos.import');
    }

    public function update(User $user, ImportJob $record): bool
    {
        return false;
    }

    public function delete(User $user, ImportJob $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
