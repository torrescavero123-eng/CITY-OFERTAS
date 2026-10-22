<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Modules\Iam\Domain\RoleName;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si WMS_REQUIRE_2FA=true, administradores y supervisores sin verificación en dos pasos
 * solo pueden ver su perfil (para configurarla) y cerrar sesión.
 */
final class EnforceTwoFactorForPrivilegedRoles
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('security.require_2fa') || ! $user instanceof User || filled($user->getAppAuthenticationSecret())) {
            return $next($request);
        }
        if (! $user->hasAnyRole([RoleName::Admin->value, RoleName::Supervisor->value])) {
            return $next($request);
        }

        $path = trim(Filament::getCurrentOrDefaultPanel()->getPath(), '/');
        if ($request->is('livewire*', "{$path}/profile*", "{$path}/logout", "{$path}/multi-factor-authentication*")) {
            return $next($request);
        }

        Notification::make()->title('Active la verificación en dos pasos')
            ->body('Su rol la exige. Configúrela aquí con una app como Google Authenticator o Microsoft Authenticator.')
            ->warning()->persistent()->send();

        return redirect()->to(Filament::getProfileUrl() ?? Filament::getUrl());
    }
}
