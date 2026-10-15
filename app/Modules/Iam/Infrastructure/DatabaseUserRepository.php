<?php

declare(strict_types=1);

namespace App\Modules\Iam\Infrastructure;

use App\Models\User;
use App\Modules\Iam\Domain\LockoutState;
use App\Modules\Iam\Domain\NewUser;
use App\Modules\Iam\Domain\RoleName;
use App\Modules\Iam\Domain\UserRecord;
use App\Modules\Iam\Domain\UserRepository;
use Illuminate\Support\Facades\DB;

final class DatabaseUserRepository implements UserRepository
{
    public function usernameExists(string $username): bool
    {
        return DB::table('users')->whereRaw('lower(username) = ?', [mb_strtolower($username)])->exists();
    }

    public function emailExists(string $email): bool
    {
        return DB::table('users')->whereRaw('lower(email) = ?', [mb_strtolower($email)])->exists();
    }

    public function insert(NewUser $user, string $passwordHash): int
    {
        $id = (int) DB::table('users')->insertGetId([
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'password' => $passwordHash,
            'default_warehouse_id' => $user->defaultWarehouseId,
            'is_active' => true,
        ], 'id');

        /** @var User $model */
        $model = User::query()->findOrFail($id);
        $model->assignRole($user->role->value);

        return $id;
    }

    public function find(int $id): ?UserRecord
    {
        $row = DB::table('users')->where('id', $id)->whereNull('deleted_at')->first(['id', 'username', 'is_active']);
        if ($row === null) {
            return null;
        }

        /** @var User $model */
        $model = User::query()->findOrFail($id);

        return new UserRecord(
            (int) $row->id,
            (string) $row->username,
            filter_var($row->is_active, FILTER_VALIDATE_BOOLEAN),
            $model->hasRole(RoleName::Admin->value),
        );
    }

    public function countActiveAdmins(): int
    {
        // FOR UPDATE sobre los administradores activos: dos desactivaciones simultáneas no pueden dejar el sistema sin admin.
        $ids = DB::select(
            'SELECT u.id FROM users u
               JOIN model_has_roles mr ON mr.model_id = u.id AND mr.model_type = ?
               JOIN roles r ON r.id = mr.role_id AND r.name = ?
              WHERE u.is_active = TRUE AND u.deleted_at IS NULL
              ORDER BY u.id FOR UPDATE OF u',
            [User::class, RoleName::Admin->value],
        );

        return count($ids);
    }

    public function setActive(int $id, bool $active): void
    {
        DB::table('users')->where('id', $id)->update(['is_active' => $active, 'updated_at' => now()]);
    }

    public function lockoutState(int $id): LockoutState
    {
        $row = DB::table('users')->where('id', $id)->first(['failed_attempts', 'locked_until']);

        return new LockoutState(
            (int) ($row->failed_attempts ?? 0),
            ($row->locked_until ?? null) === null ? null : new \DateTimeImmutable((string) $row->locked_until),
        );
    }

    public function saveLockoutState(int $id, LockoutState $state): void
    {
        DB::table('users')->where('id', $id)->update([
            'failed_attempts' => $state->failedAttempts,
            'locked_until' => $state->lockedUntil?->format('Y-m-d H:i:sP'),
        ]);
    }

    public function markLogin(int $id, \DateTimeImmutable $at): void
    {
        DB::table('users')->where('id', $id)->update(['last_login_at' => $at->format('Y-m-d H:i:sP')]);
    }
}
