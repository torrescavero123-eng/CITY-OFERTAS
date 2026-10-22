<?php

declare(strict_types=1);

use App\Modules\Iam\Application\CreateUser;
use App\Modules\Iam\Application\ResetUserPassword;
use App\Modules\Iam\Domain\IamException;
use App\Modules\Iam\Domain\LockoutState;
use App\Modules\Iam\Domain\NewUser;
use App\Modules\Iam\Domain\RoleName;
use Tests\Support\FakeHasher;
use Tests\Support\Iam\InMemoryUserRepository;
use Tests\Support\PassthroughTransaction;
use Tests\Support\RecordingAuditTrail;

function resetKit(): array
{
    $repo = new InMemoryUserRepository;
    $audit = new RecordingAuditTrail;
    $tx = new PassthroughTransaction;
    $id = (new CreateUser($repo, new FakeHasher, $tx, $audit))(new NewUser('Ana Pérez', 'ana', 'ana@cityofertas.pe', 'caballo-azul-lluvia-77', RoleName::Almacenero));

    return [$repo, $audit, new ResetUserPassword($repo, new FakeHasher, $tx, $audit), $id];
}

it('el administrador fija una contraseña nueva, que se guarda cifrada y desbloquea la cuenta', function () {
    [$repo, , $reset, $id] = resetKit();
    $repo->saveLockoutState($id, new LockoutState(5, new DateTimeImmutable('+10 minutes')));

    $reset($id, 'otra-clave-larga-segura-9', 1);

    expect($repo->rows[$id]['hash'])->toBe('hash:'.strrev('otra-clave-larga-segura-9'));
    expect($repo->lockoutState($id)->failedAttempts)->toBe(0);
    expect($repo->lockoutState($id)->lockedUntil)->toBeNull();
});

it('la auditoría registra el cambio sin guardar la contraseña', function () {
    [, $audit, $reset, $id] = resetKit();

    $reset($id, 'otra-clave-larga-segura-9', 1);

    $entry = end($audit->entries);
    expect($entry->action)->toBe('user.password_reset');
    expect($entry->actorId)->toBe(1);
    expect(json_encode($entry))->not->toContain('otra-clave');
});

it('rechaza contraseñas débiles o que contienen el usuario o el correo, y usuarios inexistentes', function () {
    [$repo, , $reset, $id] = resetKit();
    $before = $repo->rows[$id]['hash'];

    expect(fn () => $reset($id, 'corta', 1))->toThrow(IamException::class);
    expect(fn () => $reset($id, 'ana-segura-12345', 1))->toThrow(IamException::class);
    expect(fn () => $reset(999, 'otra-clave-larga-segura-9', 1))->toThrow(IamException::class);
    expect($repo->rows[$id]['hash'])->toBe($before);
});
