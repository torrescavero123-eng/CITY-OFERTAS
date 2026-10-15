<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Iam\Domain\RoleName;
use Illuminate\Support\Facades\Hash;
use Tests\Support\PanelUsers;

beforeEach(function () {
    PanelUsers::seedRoles();
});

it('wms:admin-reset crea al administrador si no existe', function () {
    $this->artisan('wms:admin-reset', ['--username' => 'admin', '--email' => 'admin@cityofertas.test'])
        ->expectsQuestion('Nueva contraseña (mínimo 12 caracteres)', 'caballo-azul-lluvia-77')
        ->expectsQuestion('Repita la contraseña', 'caballo-azul-lluvia-77')
        ->assertExitCode(0);

    $user = User::query()->where('email', 'admin@cityofertas.test')->firstOrFail();
    expect(Hash::check('caballo-azul-lluvia-77', $user->password))->toBeTrue();
    expect($user->hasRole('admin'))->toBeTrue();
});

it('wms:admin-reset recupera una cuenta bloqueada o desactivada', function () {
    $user = PanelUsers::withRole(RoleName::Supervisor, ['username' => 'admin', 'email' => 'admin@cityofertas.test', 'is_active' => false]);
    $user->forceFill(['failed_attempts' => 3, 'locked_until' => now()->addMinutes(10)])->save();

    $this->artisan('wms:admin-reset', ['--username' => 'admin', '--email' => 'admin@cityofertas.test'])
        ->expectsQuestion('Nueva contraseña (mínimo 12 caracteres)', 'otra-clave-larga-segura-9')
        ->expectsQuestion('Repita la contraseña', 'otra-clave-larga-segura-9')
        ->assertExitCode(0);

    $user->refresh();
    expect($user->is_active)->toBeTrue();
    expect($user->locked_until)->toBeNull();
    expect((int) $user->getAttribute('failed_attempts'))->toBe(0);
    expect(Hash::check('otra-clave-larga-segura-9', $user->password))->toBeTrue();
    expect($user->hasRole('admin'))->toBeTrue();
});

it('wms:admin-reset rechaza contraseñas distintas o débiles', function () {
    $this->artisan('wms:admin-reset', ['--username' => 'admin', '--email' => 'admin@cityofertas.test'])
        ->expectsQuestion('Nueva contraseña (mínimo 12 caracteres)', 'caballo-azul-lluvia-77')
        ->expectsQuestion('Repita la contraseña', 'no-coincide')
        ->assertExitCode(1);

    $this->artisan('wms:admin-reset', ['--username' => 'admin', '--email' => 'admin@cityofertas.test'])
        ->expectsQuestion('Nueva contraseña (mínimo 12 caracteres)', 'corta')
        ->expectsQuestion('Repita la contraseña', 'corta')
        ->assertExitCode(1);

    expect(User::query()->where('email', 'admin@cityofertas.test')->exists())->toBeFalse();
});

it('wms:users lista los usuarios con su estado', function () {
    PanelUsers::withRole(RoleName::Almacenero, ['username' => 'luis', 'email' => 'luis@cityofertas.test']);

    $this->artisan('wms:users')->expectsOutputToContain('luis@cityofertas.test')->assertExitCode(0);
});
