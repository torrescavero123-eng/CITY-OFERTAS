<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/** Solo quien tiene users.manage administra usuarios. No se editan ni se borran desde el panel: se activan/desactivan. */
final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function view(User $user, User $record): bool
    {
        return $user->can('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, User $record): bool
    {
        return false;
    }

    public function delete(User $user, User $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
