<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

final class WmsUsers extends Command
{
    protected $signature = 'wms:users';

    protected $description = 'Lista los usuarios del sistema y su estado de acceso (activo, bloqueado, roles)';

    public function handle(): int
    {
        $rows = User::query()->with('roles')->orderBy('id')->get()->map(static fn (User $u): array => [
            $u->id,
            $u->username,
            $u->email,
            $u->is_active ? 'sí' : 'NO',
            (string) (int) $u->getAttribute('failed_attempts'),
            $u->locked_until !== null && $u->locked_until->isFuture() ? 'BLOQUEADO hasta '.$u->locked_until->timezone('America/Lima')->format('d/m H:i') : '—',
            $u->roles->pluck('name')->implode(', ') ?: 'SIN ROL',
        ])->all();

        if ($rows === []) {
            $this->warn('No hay usuarios. Cree el administrador con: php artisan wms:admin-reset');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Usuario', 'Correo (con este se entra)', 'Activo', 'Intentos', 'Bloqueo', 'Roles'], $rows);

        return self::SUCCESS;
    }
}
