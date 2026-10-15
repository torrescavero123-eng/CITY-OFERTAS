<?php

declare(strict_types=1);

use App\Modules\Iam\Domain\Permissions;
use App\Modules\Iam\Domain\RoleName;
use App\Modules\Inventory\Domain\MovementType;

it('el administrador tiene todos los permisos', function () {
    expect(Permissions::forRole(RoleName::Admin))->toBe(Permissions::all());
});

it('ningún rol referencia permisos inexistentes ni repetidos', function () {
    foreach (RoleName::cases() as $role) {
        $perms = Permissions::forRole($role);
        expect(count($perms))->toBe(count(array_unique($perms)));
        foreach ($perms as $p) {
            expect(in_array($p, Permissions::all(), true))->toBeTrue();
        }
    }
});

it('el almacenero opera pero no aprueba ajustes ni administra', function () {
    $p = Permissions::forRole(RoleName::Almacenero);

    expect(in_array(Permissions::INVENTORY_RECEIVE, $p, true))->toBeTrue();
    expect(in_array(Permissions::INVENTORY_ADJUST_REQUEST, $p, true))->toBeTrue();
    expect(in_array(Permissions::INVENTORY_ADJUST_APPROVE, $p, true))->toBeFalse();
    expect(in_array(Permissions::USERS_MANAGE, $p, true))->toBeFalse();
    expect(in_array(Permissions::INVENTORY_OPENING_BALANCE, $p, true))->toBeFalse();
});

it('compras y lectura no escriben en inventario', function () {
    foreach ([RoleName::Compras, RoleName::Lectura] as $role) {
        $p = Permissions::forRole($role);
        foreach ([Permissions::INVENTORY_RECEIVE, Permissions::INVENTORY_TRANSFER, Permissions::INVENTORY_ADJUST_APPROVE, Permissions::CATALOG_WRITE] as $forbidden) {
            expect(in_array($forbidden, $p, true))->toBeFalse();
        }
    }
});

it('solo el administrador carga el saldo inicial y gestiona usuarios', function () {
    foreach ([RoleName::Supervisor, RoleName::Almacenero, RoleName::Compras, RoleName::Lectura] as $role) {
        $p = Permissions::forRole($role);
        expect(in_array(Permissions::INVENTORY_OPENING_BALANCE, $p, true))->toBeFalse();
        expect(in_array(Permissions::USERS_MANAGE, $p, true))->toBeFalse();
        expect(in_array(Permissions::SETTINGS_MANAGE, $p, true))->toBeFalse();
    }
});

it('todo permiso que exige el módulo Inventory existe y lo tiene algún rol', function () {
    foreach (MovementType::cases() as $type) {
        $needed = $type->requiredPermission();
        expect(in_array($needed, Permissions::all(), true))->toBeTrue();
    }
});
