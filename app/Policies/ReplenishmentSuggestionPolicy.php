<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReplenishmentSuggestion;
use App\Models\User;

final class ReplenishmentSuggestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('alerts.view');
    }

    public function view(User $user, ReplenishmentSuggestion $record): bool
    {
        return $user->can('alerts.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ReplenishmentSuggestion $record): bool
    {
        return false;
    }

    public function delete(User $user, ReplenishmentSuggestion $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
