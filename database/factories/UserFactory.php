<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $username = 'u'.Str::lower(Str::random(8));

        return [
            'name' => fake()->name(),
            'username' => $username,
            'email' => $username.'@cityofertas.test',
            'password' => 'clave-de-prueba-larga-123',
            'is_active' => true,
        ];
    }
}
