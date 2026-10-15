<?php

declare(strict_types=1);

namespace App\Modules\Iam\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Iam\Domain\IamException;
use App\Modules\Iam\Domain\NewUser;
use App\Modules\Iam\Domain\PasswordHasher;
use App\Modules\Iam\Domain\UserRepository;
use App\Shared\Domain\Transaction;

final class CreateUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    /** @return int id del usuario creado */
    public function __invoke(NewUser $user): int
    {
        return $this->transaction->run(function () use ($user): int {
            if ($this->users->usernameExists($user->username)) {
                throw IamException::usernameTaken($user->username);
            }
            if ($this->users->emailExists($user->email)) {
                throw IamException::emailTaken($user->email);
            }

            $id = $this->users->insert($user, $this->hasher->hash($user->password));

            // La contraseña NUNCA se audita.
            $this->audit->record(new AuditEntry(
                'user.created', 'user', $id, $user->actorId,
                null, ['username' => $user->username, 'email' => $user->email, 'role' => $user->role->value],
            ));

            return $id;
        });
    }
}
