<?php

declare(strict_types=1);

use App\Modules\Iam\Domain\PasswordPolicy;

it('acepta una contraseña larga y razonable', function () {
    expect(PasswordPolicy::violations('caballo-azul-lluvia-77', 'ana', 'ana@cityofertas.pe'))->toBe([]);
});

it('rechaza contraseñas cortas', function () {
    expect(PasswordPolicy::violations('corta123', 'luis', 'luis@x.pe'))->toHaveCount(1);
});

it('rechaza contraseñas que contienen el usuario o el correo', function () {
    expect(count(PasswordPolicy::violations('xx-sandro-9999-yy', 'sandro', 'otro@x.pe')) > 0)->toBeTrue();
    expect(count(PasswordPolicy::violations('xx-maria.perez-yy', 'mp', 'maria.perez@x.pe')) > 0)->toBeTrue();
});

it('rechaza contraseñas comunes o repetitivas', function () {
    expect(count(PasswordPolicy::violations('password1234', 'zzz', 'q@x.pe')) > 0)->toBeTrue();
    expect(count(PasswordPolicy::violations('aaaaaaaaaaaaaa', 'zzz', 'q@x.pe')) > 0)->toBeTrue();
});
