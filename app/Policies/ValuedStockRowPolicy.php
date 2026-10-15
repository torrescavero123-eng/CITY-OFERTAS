<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ValuedStockRow;

final class ValuedStockRowPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reports.view');
    }

    public function view(User $user, ValuedStockRow $record): bool
    {
        return $user->can('reports.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ValuedStockRow $record): bool
    {
        return false;
    }

    public function delete(User $user, ValuedStockRow $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
