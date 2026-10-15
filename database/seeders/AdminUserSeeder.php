<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Iam\Application\CreateUser;
use App\Modules\Iam\Domain\NewUser;
use App\Modules\Iam\Domain\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Crea el primer administrador desde variables de entorno (nunca contraseñas en el código):
 *   WMS_ADMIN_USERNAME, WMS_ADMIN_EMAIL, WMS_ADMIN_PASSWORD  (mínimo 12 caracteres)
 */
final class AdminUserSeeder extends Seeder
{
    public function run(CreateUser $createUser): void
    {
        $username = (string) env('WMS_ADMIN_USERNAME', '');
        $email = (string) env('WMS_ADMIN_EMAIL', '');
        $password = (string) env('WMS_ADMIN_PASSWORD', '');

        if ($username === '' || $email === '' || $password === '') {
            $this->command?->warn('Sin WMS_ADMIN_USERNAME / WMS_ADMIN_EMAIL / WMS_ADMIN_PASSWORD: no se creó administrador.');

            return;
        }
        if (DB::table('users')->whereRaw('lower(username) = ?', [mb_strtolower($username)])->exists()) {
            $this->command?->info("El administrador '{$username}' ya existe.");

            return;
        }

        $createUser(new NewUser('Administrador', $username, $email, $password, RoleName::Admin));
        $this->command?->info("Administrador '{$username}' creado.");
    }
}
