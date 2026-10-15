<?php

declare(strict_types=1);

namespace App\Modules\Iam\Application;

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Iam\Domain\IamException;
use App\Modules\Iam\Domain\UserRepository;
use App\Shared\Domain\Transaction;

final class ActivateUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Transaction $transaction,
        private readonly AuditTrail $audit,
    ) {}

    public function __invoke(int $userId, int $actorId): void
    {
        $this->transaction->run(function () use ($userId, $actorId): void {
            $user = $this->users->find($userId) ?? throw IamException::notFound($userId);

            if ($user->isActive) {
                return; // idempotente
            }

            $this->users->setActive($userId, true);
            $this->audit->record(new AuditEntry(
                'user.activated', 'user', $userId, $actorId,
                ['is_active' => false], ['is_active' => true],
            ));
        });
    }
}
