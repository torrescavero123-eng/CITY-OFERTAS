<?php

declare(strict_types=1);

namespace App\Modules\Iam\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Iam\Domain\IamException;
use App\Modules\Iam\Domain\UserRepository;
use App\Shared\Domain\Transaction;

final class DeactivateUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $userId, int $actorId): void
    {
        $this->transaction->run(function () use ($userId, $actorId): void {
            if ($userId === $actorId) {
                throw IamException::cannotDeactivateSelf();
            }

            $user = $this->users->find($userId) ?? throw IamException::notFound($userId);

            if (! $user->isActive) {
                return; // idempotente
            }
            if ($user->isAdmin && $this->users->countActiveAdmins() <= 1) {
                throw IamException::lastAdmin();
            }

            $this->users->setActive($userId, false);
            $this->audit->record(new AuditEntry(
                'user.deactivated', 'user', $userId, $actorId,
                ['is_active' => true], ['is_active' => false],
            ));
        });
    }
}
