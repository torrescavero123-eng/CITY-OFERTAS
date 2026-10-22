<?php

declare(strict_types=1);

namespace App\Modules\Iam\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Iam\Domain\IamException;
use App\Modules\Iam\Domain\LockoutState;
use App\Modules\Iam\Domain\PasswordHasher;
use App\Modules\Iam\Domain\PasswordPolicy;
use App\Modules\Iam\Domain\UserRepository;
use App\Shared\Domain\Transaction;

/**
 * El administrador fija una contraseña nueva a quien la olvidó. También desbloquea la cuenta.
 * La contraseña cumple la misma política (NIST) y NUNCA se guarda en la auditoría.
 */
final class ResetUserPassword
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $userId, string $newPassword, int $actorId): void
    {
        $this->transaction->run(function () use ($userId, $newPassword, $actorId): void {
            $user = $this->users->find($userId) ?? throw IamException::notFound($userId);

            $violations = PasswordPolicy::violations($newPassword, $user->username, $user->email);
            if ($violations !== []) {
                throw IamException::invalid(implode(' ', $violations));
            }

            $this->users->setPasswordHash($userId, $this->hasher->hash($newPassword));
            $this->users->saveLockoutState($userId, new LockoutState(0, null));
            $this->audit->record(new AuditEntry('user.password_reset', 'user', $userId, $actorId, null, ['by_admin' => $actorId !== $userId]));
        });
    }
}
