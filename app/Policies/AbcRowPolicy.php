<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AbcRow;
use App\Models\User;

final class AbcRowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reports.view');
    }

    public function view(User $user, AbcRow $record): bool
    {
        return $user->can('reports.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AbcRow $record): bool
    {
        return false;
    }

    public function delete(User $user, AbcRow $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
