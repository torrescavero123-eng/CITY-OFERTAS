<?php

declare(strict_types=1);

namespace Tests\Support\Iam;

use App\Modules\Iam\Domain\LockoutState;
use App\Modules\Iam\Domain\NewUser;
use App\Modules\Iam\Domain\RoleName;
use App\Modules\Iam\Domain\UserRecord;
use App\Modules\Iam\Domain\UserRepository;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<int, array{username: string, email: string, active: bool, role: RoleName, hash: string, lockout: LockoutState, lastLogin: ?\DateTimeImmutable}> */
    public array $rows = [];

    public function usernameExists(string $username): bool
    {
        foreach ($this->rows as $r) {
            if ($r['username'] === $username) {
                return true;
            }
        }

        return false;
    }

    public function emailExists(string $email): bool
    {
        foreach ($this->rows as $r) {
            if ($r['email'] === $email) {
                return true;
            }
        }

        return false;
    }

    public function insert(NewUser $user, string $passwordHash): int
    {
        $id = count($this->rows) + 1;
        $this->rows[$id] = [
            'username' => $user->username, 'email' => $user->email, 'active' => true,
            'role' => $user->role, 'hash' => $passwordHash, 'lockout' => new LockoutState(0, null), 'lastLogin' => null,
        ];

        return $id;
    }

    public function find(int $id): ?UserRecord
    {
        $r = $this->rows[$id] ?? null;

        return $r === null ? null : new UserRecord($id, $r['username'], $r['active'], $r['role'] === RoleName::Admin);
    }

    public function countActiveAdmins(): int
    {
        return count(array_filter($this->rows, static fn (array $r): bool => $r['active'] && $r['role'] === RoleName::Admin));
    }

    public function setActive(int $id, bool $active): void
    {
        $this->rows[$id]['active'] = $active;
    }

    public function lockoutState(int $id): LockoutState
    {
        return $this->rows[$id]['lockout'];
    }

    public function saveLockoutState(int $id, LockoutState $state): void
    {
        $this->rows[$id]['lockout'] = $state;
    }

    public function markLogin(int $id, \DateTimeImmutable $at): void
    {
        $this->rows[$id]['lastLogin'] = $at;
    }
}
