<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

final class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }

    public function view(User $user, AuditLog $record): bool
    {
        return $user->can('audit.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $record): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
