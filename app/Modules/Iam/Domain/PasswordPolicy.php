<?php

declare(strict_types=1);

namespace App\Modules\Iam\Domain;

/** Política alineada con NIST 800-63B: prima la longitud, no las reglas de composición. */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    public const MAX_LENGTH = 128;

    private const COMMON = [
        'contrasena1234', 'contraseña1234', 'password1234', 'administrador', 'administrador1',
        '123456789012', 'qwertyuiop12', 'admin1234567', 'cityofertas1',
    ];

    /** @return list<string> mensajes de incumplimiento (vacío = válida) */
    public static function violations(string $password, string $username, string $email): array
    {
        $errors = [];
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH) {
            $errors[] = 'La contraseña debe tener al menos '.self::MIN_LENGTH.' caracteres.';
        }
        if ($length > self::MAX_LENGTH) {
            $errors[] = 'La contraseña no puede superar '.self::MAX_LENGTH.' caracteres.';
        }
        if (mb_strlen($username) >= 3 && mb_stripos($password, $username) !== false) {
            $errors[] = 'La contraseña no puede contener el nombre de usuario.';
        }
        $local = explode('@', $email)[0];
        if (mb_strlen($local) >= 3 && mb_stripos($password, $local) !== false) {
            $errors[] = 'La contraseña no puede contener su correo.';
        }
        if (in_array(mb_strtolower($password), self::COMMON, true) || preg_match('/^(.)\1+$/u', $password) === 1) {
            $errors[] = 'La contraseña es demasiado común.';
        }

        return $errors;
    }
}
