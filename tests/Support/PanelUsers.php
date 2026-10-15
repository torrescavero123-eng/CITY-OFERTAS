<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Modules\Iam\Domain\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;

/** Ayudante de pruebas: usuarios reales (Eloquent) con su rol. */
final class PanelUsers
{
    public static function seedRoles(): void
    {
        (new RolesAndPermissionsSeeder)->run();
    }

    /** @param array<string, mixed> $attributes */
    public static function withRole(RoleName $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }
}
