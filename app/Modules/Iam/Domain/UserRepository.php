<?php

declare(strict_types=1);

namespace App\Modules\Iam\Domain;

interface UserRepository
{
    public function usernameExists(string $username): bool;

    public function emailExists(string $email): bool;

    public function insert(NewUser $user, string $passwordHash): int;

    public function find(int $id): ?UserRecord;

    /** Cantidad de administradores activos (bloquea filas para evitar carreras al desactivar). */
    public function countActiveAdmins(): int;

    public function setActive(int $id, bool $active): void;

    public function lockoutState(int $id): LockoutState;

    public function saveLockoutState(int $id, LockoutState $state): void;

    public function markLogin(int $id, \DateTimeImmutable $at): void;
}
