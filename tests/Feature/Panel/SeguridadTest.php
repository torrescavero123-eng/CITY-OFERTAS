<?php

declare(strict_types=1);

use App\Filament\Resources\Audit\AuditResource;
use App\Filament\Resources\Audit\Pages\ListAuditLogs;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Http\Middleware\EnforceTwoFactorForPrivilegedRoles;
use App\Modules\Iam\Domain\RoleName;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\PanelUsers;

beforeEach(function () {
    PanelUsers::seedRoles();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function segRun(string $uri, $user): Response
{
    $request = Request::create($uri);
    $request->setUserResolver(static fn () => $user);

    return (new EnforceTwoFactorForPrivilegedRoles)->handle($request, static fn (): Response => new Response('ok'));
}

it('el administrador restablece la contraseña de otra persona desde la lista y se desbloquea', function () {
    $admin = PanelUsers::withRole(RoleName::Admin);
    $worker = PanelUsers::withRole(RoleName::Almacenero);
    $worker->forceFill(['failed_attempts' => 4, 'locked_until' => now()->addMinutes(10)])->save();
    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->mountTableAction('resetPassword', $worker->id)
        ->set('mountedActions.0.data', ['password' => 'clave-nueva-muy-larga-55', 'password_confirmation' => 'clave-nueva-muy-larga-55'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $worker->refresh();
    expect(Hash::check('clave-nueva-muy-larga-55', $worker->password))->toBeTrue();
    expect($worker->locked_until)->toBeNull();
    expect(DB::table('audit_logs')->where('action', 'user.password_reset')->where('entity_id', $worker->id)->exists())->toBeTrue();
});

it('la auditoría solo la ve quien tiene el permiso y la lista abre', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Almacenero));
    expect(AuditResource::canViewAny())->toBeFalse();

    $this->actingAs(PanelUsers::withRole(RoleName::Admin));
    expect(AuditResource::canViewAny())->toBeTrue();
    Livewire::test(ListAuditLogs::class)->assertOk();
});

it('con la exigencia activa, admin y supervisor sin verificación en dos pasos solo ven su perfil', function () {
    config(['security.require_2fa' => true]);
    $admin = PanelUsers::withRole(RoleName::Admin);
    $supervisor = PanelUsers::withRole(RoleName::Supervisor);

    expect(segRun('/admin/products', $admin)->isRedirect())->toBeTrue();
    expect(segRun('/admin/products', $supervisor)->isRedirect())->toBeTrue();
    expect(segRun('/admin/profile', $admin)->getContent())->toBe('ok');
    expect(segRun('/livewire/update', $admin)->getContent())->toBe('ok');
    expect(segRun('/admin/logout', $admin)->getContent())->toBe('ok');
});

it('no afecta a otros roles, a quien ya la configuró ni cuando la exigencia está apagada', function () {
    $admin = PanelUsers::withRole(RoleName::Admin);
    $worker = PanelUsers::withRole(RoleName::Almacenero);

    config(['security.require_2fa' => false]);
    expect(segRun('/admin/products', $admin)->getContent())->toBe('ok');

    config(['security.require_2fa' => true]);
    expect(segRun('/admin/products', $worker)->getContent())->toBe('ok');

    $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    expect(segRun('/admin/products', $admin->refresh())->getContent())->toBe('ok');
});
