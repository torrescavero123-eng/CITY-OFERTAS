<?php

declare(strict_types=1);

use App\Modules\Iam\Domain\Permissions;
use App\Modules\Iam\Domain\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('el seeder crea los 5 roles con exactamente los permisos de la matriz', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    foreach (RoleName::cases() as $role) {
        $actual = Role::findByName($role->value, 'web')->permissions->pluck('name')->all();
        $expected = Permissions::forRole($role);
        sort($actual);
        sort($expected);

        expect($actual)->toBe($expected);
    }
});

it('el seeder es idempotente', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Permission::query()->count())->toBe(count(Permissions::all()));
    expect(Role::query()->count())->toBe(count(RoleName::cases()));
});
