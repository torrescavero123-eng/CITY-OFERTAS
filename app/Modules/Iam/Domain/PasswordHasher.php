<?php

declare(strict_types=1);

namespace App\Modules\Iam\Domain;

interface PasswordHasher
{
    public function hash(string $plain): string;
}
