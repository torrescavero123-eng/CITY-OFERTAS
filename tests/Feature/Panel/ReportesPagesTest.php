<?php

declare(strict_types=1);

use App\Filament\Resources\Reports\Abc\AbcResource;
use App\Filament\Resources\Reports\Abc\Pages\ListAbc;
use App\Filament\Resources\Reports\ValuedStock\Pages\ListValuedStock;
use App\Filament\Resources\Reports\ValuedStock\ValuedStockResource;
use App\Filament\Resources\Reports\Waste\Pages\ListWaste;
use App\Filament\Resources\Reports\Waste\WasteResource;
use App\Modules\Iam\Domain\RoleName;
use App\Modules\Inventory\Application\RegisterMovement;
use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Operations\Application\ApproveAdjustment;
use App\Modules\Operations\Application\RequestAdjustment;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\NewAdjustment;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\Fixtures;
use Tests\Support\MovementFactory as M;
use Tests\Support\PanelUsers;

beforeEach(function () {
    PanelUsers::seedRoles();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('las vistas de reportes calculan valor, clase ABC y mermas', function () {
    $actor = Fixtures::user();
    $loc = Fixtures::location('BOD');
    $register = app(RegisterMovement::class);
    $a = Fixtures::product();
    $b = Fixtures::product();
    $c = Fixtures::product();
    $d = Fixtures::product();
    foreach ([[$a, 100, 50], [$b, 20, 5], [$c, 20, 1]] as [$p, $buy, $sold]) {
        $register(M::purchase($p, $loc, $buy, '2', $actor));
        $register(M::sale($p, $loc, $sold, $actor));
    }
    $register(M::purchase($d, $loc, 5, '3', $actor));

    $class = static fn (int $p): string => (string) DB::table('v_report_abc')->where('product_id', $p)->value('abc_class');
    expect($class($a))->toBe('A');
    expect($class($b))->toBe('B');
    expect($class($c))->toBe('C');
    expect($class($d))->toBe('SIN_VENTAS');
    expect((float) DB::table('v_report_abc')->where('product_id', $a)->value('value_90d'))->toBe(100.0);

    expect((float) DB::table('v_report_valued_stock')->where('product_id', $a)->sum('value'))->toBe(100.0); // 50 u. x S/ 2
    expect((float) DB::table('v_report_valued_stock')->where('product_id', $d)->sum('value'))->toBe(15.0);  // 5 u. x S/ 3

    $worker = PanelUsers::withRole(RoleName::Almacenero);
    $supervisor = PanelUsers::withRole(RoleName::Supervisor);
    $reason = (int) DB::table('movement_reasons')->where('code', 'MERMA_DANIO')->value('id');
    $req = app(RequestAdjustment::class)(new NewAdjustment(AdjustmentKind::WriteOff, $d, $loc, Quantity::of('2'), $reason, 'Se mojaron con la lluvia', $worker->id));
    app(ApproveAdjustment::class)($req['id'], $supervisor->id, null);

    $waste = DB::table('v_report_waste')->where('sku', DB::table('products')->where('id', $d)->value('sku'))->first();
    expect($waste)->not->toBeNull();
    expect($waste->type)->toBe('MERMA');
    expect((float) $waste->value)->toBe(6.0);
    expect($waste->approver_name)->toBe($supervisor->name);
});

it('las pantallas de reportes abren y el administrador puede descargar', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Admin));

    Livewire::test(ListValuedStock::class)->assertOk()->assertActionVisible('export');
    Livewire::test(ListAbc::class)->assertOk()->assertActionVisible('export');
    Livewire::test(ListWaste::class)->assertOk()->assertActionVisible('export');
});

it('solo lectura puede ver los reportes', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Lectura));

    expect(ValuedStockResource::canViewAny())->toBeTrue();
    expect(AbcResource::canViewAny())->toBeTrue();
    expect(WasteResource::canViewAny())->toBeTrue();
});
