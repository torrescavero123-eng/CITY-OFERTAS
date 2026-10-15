<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Iam\Application\CreateUser;
use App\Modules\Iam\Application\DeactivateUser;
use App\Modules\Iam\Application\RecordLoginFailure;
use App\Modules\Iam\Domain\IamException;
use App\Modules\Iam\Domain\NewUser;
use App\Modules\Iam\Domain\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function makeUser(string $username, RoleName $role, ?string $email = null): int
{
    return app(CreateUser::class)(new NewUser('Usuario Prueba', $username, $email ?? "{$username}@cityofertas.test", 'caballo-azul-lluvia-77', $role));
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('crea el usuario con hash, rol y permisos efectivos', function () {
    $id = makeUser('almacenero1', RoleName::Almacenero);

    $user = User::query()->findOrFail($id);
    expect(Hash::check('caballo-azul-lluvia-77', $user->password))->toBeTrue();
    expect($user->hasRole('almacenero'))->toBeTrue();
    expect($user->can('inventory.receive'))->toBeTrue();
    expect($user->can('inventory.adjust.approve'))->toBeFalse();
    expect($user->can('users.manage'))->toBeFalse();
});

it('audita la creación sin guardar la contraseña', function () {
    $id = makeUser('supervisor1', RoleName::Supervisor);

    $new = (string) DB::table('audit_logs')->where('action', 'user.created')->where('entity_id', $id)->value('new_values');

    expect($new)->toContain('supervisor1');
    expect($new)->not->toContain('caballo');
    expect($new)->not->toContain('password');
});

it('rechaza usuario repetido sin distinguir mayúsculas', function () {
    makeUser('ana', RoleName::Lectura);

    expect(fn () => makeUser('ANA', RoleName::Lectura, 'otra@cityofertas.test'))->toThrow(IamException::class, 'ya existe');
});

it('protege al último administrador activo', function () {
    $admin1 = makeUser('admin1', RoleName::Admin);
    $supervisor = makeUser('super1', RoleName::Supervisor);
    $deactivate = app(DeactivateUser::class);

    expect(fn () => $deactivate($admin1, $supervisor))->toThrow(IamException::class, 'último administrador');

    $admin2 = makeUser('admin2', RoleName::Admin);
    $deactivate($admin1, $admin2);

    expect((bool) DB::table('users')->where('id', $admin1)->value('is_active'))->toBeFalse();
});

it('bloquea la cuenta tras 5 intentos fallidos', function () {
    $id = makeUser('luis', RoleName::Almacenero);
    $fail = app(RecordLoginFailure::class);

    for ($i = 0; $i < 5; $i++) {
        $fail($id);
    }

    expect(DB::table('users')->where('id', $id)->value('locked_until'))->not->toBeNull();
    expect((int) DB::table('users')->where('id', $id)->value('failed_attempts'))->toBe(0);
});
