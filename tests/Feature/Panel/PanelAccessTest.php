<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Iam\Domain\RoleName;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Support\PanelUsers;

beforeEach(function () {
    PanelUsers::seedRoles();
});

it('cada rol con cuenta activa puede entrar al panel', function () {
    $panel = Filament::getPanel('admin');

    foreach (RoleName::cases() as $role) {
        expect(PanelUsers::withRole($role)->canAccessPanel($panel))->toBeTrue();
    }
});

it('no entran las cuentas desactivadas, bloqueadas o sin rol', function () {
    $panel = Filament::getPanel('admin');

    expect(PanelUsers::withRole(RoleName::Admin, ['is_active' => false])->canAccessPanel($panel))->toBeFalse();

    $locked = PanelUsers::withRole(RoleName::Admin);
    $locked->forceFill(['locked_until' => now()->addMinutes(10)])->save();
    expect($locked->refresh()->canAccessPanel($panel))->toBeFalse();

    $expired = PanelUsers::withRole(RoleName::Admin);
    $expired->forceFill(['locked_until' => now()->subMinute()])->save();
    expect($expired->refresh()->canAccessPanel($panel))->toBeTrue();

    expect(User::factory()->create()->canAccessPanel($panel))->toBeFalse();
});

it('5 intentos fallidos bloquean la cuenta y un acierto durante el bloqueo no la libera', function () {
    $user = PanelUsers::withRole(RoleName::Almacenero);

    for ($i = 0; $i < 5; $i++) {
        Event::dispatch(new Failed('web', $user, ['email' => $user->email]));
    }
    expect(DB::table('users')->where('id', $user->id)->value('locked_until'))->not->toBeNull();

    Event::dispatch(new Login('web', $user->refresh(), false));

    expect(DB::table('users')->where('id', $user->id)->value('locked_until'))->not->toBeNull();
    expect(DB::table('users')->where('id', $user->id)->value('last_login_at'))->toBeNull();
    expect($user->refresh()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('un acceso correcto registra el último ingreso', function () {
    $user = PanelUsers::withRole(RoleName::Supervisor);

    Event::dispatch(new Login('web', $user, false));

    expect(DB::table('users')->where('id', $user->id)->value('last_login_at'))->not->toBeNull();
});
