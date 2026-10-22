<?php

declare(strict_types=1);

namespace App\Modules\Iam\Domain;

final readonly class UserRecord
{
    public function __construct(
        public int $id,
        public string $username,
        public bool $isActive,
        public bool $isAdmin,
        public string $email = '',
    ) {}
}
