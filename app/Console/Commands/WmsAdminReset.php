<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditTrail;
use App\Modules\Iam\Application\CreateUser;
use App\Modules\Iam\Domain\IamException;
use App\Modules\Iam\Domain\NewUser;
use App\Modules\Iam\Domain\PasswordPolicy;
use App\Modules\Iam\Domain\RoleName;
use Illuminate\Console\Command;

/**
 * Crea o recupera al administrador: fija una contraseña nueva, lo activa, lo desbloquea y le deja el rol Administrador.
 * La contraseña se pide de forma interactiva (no queda en el historial ni tiene problemas con caracteres especiales).
 */
final class WmsAdminReset extends Command
{
    protected $signature = 'wms:admin-reset {--username=admin : Usuario} {--email= : Correo con el que se entra al panel}';

    protected $description = 'Crea o recupera al administrador (contraseña nueva, activo, desbloqueado, rol Administrador)';

    public function handle(CreateUser $createUser, AuditTrail $audit): int
    {
        $username = mb_strtolower(trim((string) $this->option('username')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Correo con el que entrará al panel'))));

        $password = (string) $this->secret('Nueva contraseña (mínimo 12 caracteres)');
        if ($password !== (string) $this->secret('Repita la contraseña')) {
            $this->error('Las contraseñas no coinciden.');

            return self::FAILURE;
        }

        $violations = PasswordPolicy::violations($password, $username, $email);
        if ($violations !== []) {
            foreach ($violations as $v) {
                $this->error($v);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('username', $username)->orWhere('email', $email)->first();

        try {
            if ($user === null) {
                $createUser(new NewUser('Administrador', $username, $email, $password, RoleName::Admin));
                $this->info("Administrador creado. Entre con el correo {$email}.");

                return self::SUCCESS;
            }

            $user->forceFill([
                'email' => $email,
                'password' => $password, // el modelo la guarda cifrada
                'is_active' => true,
                'failed_attempts' => 0,
                'locked_until' => null,
            ])->save();
            $user->syncRoles([RoleName::Admin->value]);
            $audit->record(new AuditEntry('user.password_reset_cli', 'user', (int) $user->id, null, null, ['username' => $user->username]));
        } catch (IamException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Listo: '{$user->username}' quedó activo, desbloqueado y con la contraseña nueva. Entre con el correo {$email}.");

        return self::SUCCESS;
    }
}
