<?php

declare(strict_types=1);

use App\Filament\Resources\Counts\CountResource;
use App\Filament\Resources\Counts\Pages\ListCounts;
use App\Filament\Resources\Imports\ImportResource;
use App\Filament\Resources\Imports\Pages\ListImports;
use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Modules\Iam\Domain\RoleName;
use App\Modules\Inventory\Application\RegisterMovement;
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

function supBodega(): int
{
    return (int) DB::table('warehouses')->where('code', 'BOD')->value('id');
}

it('cada rol ve en el menú solo lo que le corresponde (ubicaciones, conteos, ventas)', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Supervisor));
    expect(LocationResource::canViewAny())->toBeTrue();
    expect(CountResource::canViewAny())->toBeTrue();
    expect(CountResource::canCreate())->toBeTrue();
    expect(ImportResource::canViewAny())->toBeTrue();

    $this->actingAs(PanelUsers::withRole(RoleName::Almacenero));
    expect(LocationResource::canViewAny())->toBeTrue();
    expect(CountResource::canViewAny())->toBeTrue();
    expect(CountResource::canCreate())->toBeFalse(); // contar sí, iniciar/cerrar no
    expect(ImportResource::canViewAny())->toBeFalse();

    $this->actingAs(PanelUsers::withRole(RoleName::Lectura));
    expect(LocationResource::canViewAny())->toBeTrue();
    expect(ImportResource::canViewAny())->toBeFalse();
});

it('el supervisor genera ubicaciones en lote desde la lista', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Supervisor));

    Livewire::test(ListLocations::class)
        ->mountAction('generate')
        ->set('mountedActions.0.data', [
            'warehouse_id' => supBodega(), 'zone' => 7, 'aisle_from' => 1, 'aisle_to' => 2, 'rack_from' => 1, 'rack_to' => 2, 'level_from' => 1, 'level_to' => 1,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Ubicaciones generadas');

    expect(DB::table('locations')->where('code', 'like', 'BOD-Z7-%')->count())->toBe(4);
    expect(DB::table('locations')->where('code', 'BOD-Z7-P02-A02-N1')->exists())->toBeTrue();
});

it('conteo completo desde las pantallas: iniciar, contar, enviar y cerrar', function () {
    $supervisor = PanelUsers::withRole(RoleName::Supervisor);
    $worker = PanelUsers::withRole(RoleName::Almacenero);
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD', ['zone' => 'ZS1']);
    app(RegisterMovement::class)(M::purchase($product, $loc, 10, '2', $supervisor->id));

    $this->actingAs($supervisor);
    Livewire::test(ListCounts::class)->mountAction('start')
        ->set('mountedActions.0.data', ['warehouse_id' => supBodega(), 'scope_type' => 'ZONE', 'scope_value' => 'ZS1'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified();
    $count = (int) DB::table('stock_counts')->orderByDesc('id')->value('id');
    expect((bool) DB::table('locations')->where('id', $loc)->value('is_frozen'))->toBeTrue();

    $this->actingAs($worker);
    Livewire::test(ListCounts::class)
        ->assertTableActionHidden('close', $count)
        ->mountTableAction('count', $count)
        ->set('mountedActions.0.data', ['product_id' => $product, 'location_id' => $loc, 'quantity' => '10'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->callTableAction('submit', $count);
    expect(DB::table('stock_counts')->where('id', $count)->value('status'))->toBe('REVIEW');

    $this->actingAs($supervisor);
    Livewire::test(ListCounts::class)->callTableAction('close', $count);

    expect(DB::table('stock_counts')->where('id', $count)->value('status'))->toBe('CLOSED');
    expect((bool) DB::table('locations')->where('id', $loc)->value('is_frozen'))->toBeFalse();
});

it('el supervisor ve la pantalla de ventas del POS con sus dos acciones; el almacenero no entra', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Supervisor));
    Livewire::test(ListImports::class)->assertOk()->assertActionVisible('simulate')->assertActionVisible('commit');

    $this->actingAs(PanelUsers::withRole(RoleName::Almacenero));
    Livewire::test(ListImports::class)->assertForbidden();
});
