<?php

declare(strict_types=1);

namespace App\Modules\Iam\Domain;

final class IamException extends \DomainException
{
    private function __construct(string $message, public readonly string $errorCode)
    {
        parent::__construct($message);
    }

    public static function invalid(string $message): self
    {
        return new self($message, 'INVALID_USER');
    }

    public static function usernameTaken(string $username): self
    {
        return new self("El usuario '{$username}' ya existe.", 'USERNAME_TAKEN');
    }

    public static function emailTaken(string $email): self
    {
        return new self("El correo '{$email}' ya está registrado.", 'EMAIL_TAKEN');
    }

    public static function notFound(int $id): self
    {
        return new self("El usuario {$id} no existe.", 'USER_NOT_FOUND');
    }

    public static function cannotDeactivateSelf(): self
    {
        return new self('No puede desactivar su propia cuenta.', 'CANNOT_DEACTIVATE_SELF');
    }

    public static function lastAdmin(): self
    {
        return new self('No se puede desactivar al último administrador activo.', 'LAST_ADMIN');
    }
}
