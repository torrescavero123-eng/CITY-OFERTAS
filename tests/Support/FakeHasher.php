<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Iam\Domain\PasswordHasher;

final class FakeHasher implements PasswordHasher
{
    public function hash(string $plain): string
    {
        return 'hash:'.strrev($plain);
    }
}
