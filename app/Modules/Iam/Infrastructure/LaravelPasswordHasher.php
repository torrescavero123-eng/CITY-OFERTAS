<?php

declare(strict_types=1);

namespace App\Modules\Iam\Infrastructure;

use App\Modules\Iam\Domain\PasswordHasher;
use Illuminate\Support\Facades\Hash;

/** Usa el driver configurado en HASH_DRIVER (argon2id en este proyecto). */
final class LaravelPasswordHasher implements PasswordHasher
{
    public function hash(string $plain): string
    {
        return Hash::make($plain);
    }
}
