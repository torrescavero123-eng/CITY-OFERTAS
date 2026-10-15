<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Modules\Iam\Domain\RoleName;
use Tests\Support\Fixtures;
use Tests\Support\PanelUsers;

beforeEach(function () {
    PanelUsers::seedRoles();
});

it('el catálogo se ve con catalog.view y se escribe solo con catalog.write', function () {
    $almacenero = PanelUsers::withRole(RoleName::Almacenero);
    $supervisor = PanelUsers::withRole(RoleName::Supervisor);

    foreach ([Product::class, Category::class, Brand::class] as $model) {
        expect($almacenero->can('viewAny', $model))->toBeTrue();
        expect($almacenero->can('create', $model))->toBeFalse();
        expect($supervisor->can('viewAny', $model))->toBeTrue();
        expect($supervisor->can('create', $model))->toBeTrue();
    }
});

it('nadie puede borrar productos: se dan de baja', function () {
    $admin = PanelUsers::withRole(RoleName::Admin);
    $product = Product::query()->findOrFail(Fixtures::product());

    expect($admin->can('delete', $product))->toBeFalse();
    expect($admin->can('deleteAny', Product::class))->toBeFalse();
});

it('solo el administrador gestiona usuarios y nadie los edita ni borra desde el panel', function () {
    $admin = PanelUsers::withRole(RoleName::Admin);
    $supervisor = PanelUsers::withRole(RoleName::Supervisor);

    expect($admin->can('viewAny', User::class))->toBeTrue();
    expect($admin->can('create', User::class))->toBeTrue();
    expect($admin->can('update', $supervisor))->toBeFalse();
    expect($admin->can('delete', $supervisor))->toBeFalse();
    expect($supervisor->can('viewAny', User::class))->toBeFalse();
    expect($supervisor->can('create', User::class))->toBeFalse();
});
