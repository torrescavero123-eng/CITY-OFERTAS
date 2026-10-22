<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario del sistema. Los permisos se consultan siempre con can('permiso'), nunca por nombre de rol.
 *
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string $email
 * @property bool $is_active
 * @property Carbon|null $locked_until
 * @property Carbon|null $last_login_at
 */
final class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use Notifiable;
    use SoftDeletes;

    protected string $guard_name = 'web';

    /** @var list<string> */
    protected $fillable = ['name', 'username', 'email', 'password', 'default_warehouse_id', 'is_active'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'locked_until' => 'datetime',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'last_login_at' => 'datetime',
        ];
    }

    /** Solo cuentas activas, no bloqueadas y con al menos un rol pueden entrar al panel. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && ($this->locked_until === null || $this->locked_until->isPast())
            && $this->roles()->exists();
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
