<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Iam\Domain\Permissions;
use App\Modules\Iam\Domain\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Idempotente: se puede ejecutar las veces que haga falta (sincroniza con Permissions::forRole). */
final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (RoleName::cases() as $role) {
            Role::findOrCreate($role->value, 'web')->syncPermissions(Permissions::forRole($role));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
