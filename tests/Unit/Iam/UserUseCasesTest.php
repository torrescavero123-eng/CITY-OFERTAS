<?php

declare(strict_types=1);

use App\Modules\Iam\Application\CreateUser;
use App\Modules\Iam\Application\DeactivateUser;
use App\Modules\Iam\Application\RecordLoginFailure;
use App\Modules\Iam\Application\RecordLoginSuccess;
use App\Modules\Iam\Domain\IamException;
use App\Modules\Iam\Domain\NewUser;
use App\Modules\Iam\Domain\RoleName;
use Tests\Support\FakeHasher;
use Tests\Support\FixedClock;
use Tests\Support\Iam\InMemoryUserRepository;
use Tests\Support\PassthroughTransaction;
use Tests\Support\RecordingAuditTrail;

function iamKit(): array
{
    $repo = new InMemoryUserRepository;
    $audit = new RecordingAuditTrail;
    $tx = new PassthroughTransaction;

    return [
        $repo, $audit,
        new CreateUser($repo, new FakeHasher, $tx, $audit),
        new DeactivateUser($repo, $tx, $audit),
    ];
}

function newUser(string $username = 'ana', string $email = 'ana@cityofertas.pe', RoleName $role = RoleName::Almacenero): NewUser
{
    return new NewUser('Ana  Pérez', $username, $email, 'caballo-azul-lluvia-77', $role);
}

it('crea un usuario normalizado, con hash y auditoría sin contraseña', function () {
    [$repo, $audit, $create] = iamKit();

    $id = $create(new NewUser('  Ana   Pérez ', 'ANA', 'Ana@CityOfertas.PE', 'caballo-azul-lluvia-77', RoleName::Supervisor, null, 99));

    expect($repo->rows[$id]['username'])->toBe('ana');
    expect($repo->rows[$id]['email'])->toBe('ana@cityofertas.pe');
    expect($repo->rows[$id]['hash'])->not->toBe('caballo-azul-lluvia-77');
    expect($audit->entries)->toHaveCount(1);
    expect($audit->entries[0]->action)->toBe('user.created');
    expect($audit->entries[0]->actorId)->toBe(99);
    expect(json_encode($audit->entries[0]))->not->toContain('caballo');
});

it('rechaza usuario o correo repetidos', function () {
    [, , $create] = iamKit();
    $create(newUser());

    expect(fn () => $create(newUser('ana', 'otra@x.pe')))->toThrow(IamException::class, 'ya existe');
    expect(fn () => $create(newUser('otra', 'ana@cityofertas.pe')))->toThrow(IamException::class, 'ya está registrado');
});

it('valida los datos del usuario y la contraseña', function () {
    expect(fn () => new NewUser('A', 'ana', 'ana@x.pe', 'caballo-azul-lluvia-77', RoleName::Lectura))->toThrow(IamException::class, 'nombre');
    expect(fn () => new NewUser('Ana', 'A N A', 'ana@x.pe', 'caballo-azul-lluvia-77', RoleName::Lectura))->toThrow(IamException::class, 'usuario');
    expect(fn () => new NewUser('Ana', 'ana', 'no-es-correo', 'caballo-azul-lluvia-77', RoleName::Lectura))->toThrow(IamException::class, 'correo');
    expect(fn () => new NewUser('Ana', 'ana', 'ana@x.pe', 'corta', RoleName::Lectura))->toThrow(IamException::class, 'al menos 12');
});

it('no permite desactivarse a sí mismo', function () {
    [, , $create, $deactivate] = iamKit();
    $id = $create(newUser());

    expect(fn () => $deactivate($id, $id))->toThrow(IamException::class, 'propia cuenta');
});

it('no permite desactivar al último administrador, pero sí a uno de varios', function () {
    [$repo, $audit, $create, $deactivate] = iamKit();
    $admin1 = $create(newUser('admin1', 'a1@x.pe', RoleName::Admin));
    $admin2 = $create(newUser('admin2', 'a2@x.pe', RoleName::Admin));

    $deactivate($admin1, $admin2);
    expect($repo->rows[$admin1]['active'])->toBeFalse();

    expect(fn () => $deactivate($admin2, $admin1))->toThrow(IamException::class, 'último administrador');
    expect($repo->rows[$admin2]['active'])->toBeTrue();
});

it('desactivar es idempotente y audita una sola vez', function () {
    [$repo, $audit, $create, $deactivate] = iamKit();
    $admin = $create(newUser('admin', 'ad@x.pe', RoleName::Admin));
    $worker = $create(newUser('luis', 'luis@x.pe'));

    $deactivate($worker, $admin);
    $deactivate($worker, $admin);

    $actions = array_map(static fn ($e) => $e->action, $audit->entries);
    expect(count(array_filter($actions, static fn ($a) => $a === 'user.deactivated')))->toBe(1);
});

it('desactivar un usuario inexistente falla', function () {
    [, , , $deactivate] = iamKit();

    expect(fn () => $deactivate(404, 1))->toThrow(IamException::class, 'no existe');
});

it('el quinto fallo bloquea y un login correcto limpia el estado', function () {
    [$repo, , $create] = iamKit();
    $id = $create(newUser());
    $clock = new FixedClock;
    $fail = new RecordLoginFailure($repo, $clock);

    for ($i = 0; $i < 4; $i++) {
        $fail($id);
    }
    expect($repo->lockoutState($id)->failedAttempts)->toBe(4);

    $state = $fail($id);
    expect($state->lockedUntil !== null)->toBeTrue();

    (new RecordLoginSuccess($repo, $clock))($id);
    expect($repo->lockoutState($id)->lockedUntil)->toBeNull();
    expect($repo->rows[$id]['lastLogin'] !== null)->toBeTrue();
});
