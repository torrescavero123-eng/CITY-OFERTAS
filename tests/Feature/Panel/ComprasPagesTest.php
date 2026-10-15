<?php

declare(strict_types=1);

use App\Filament\Resources\Replenishment\Pages\ListReplenishment;
use App\Filament\Resources\Replenishment\ReplenishmentResource;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Widgets\CriticalStockTable;
use App\Filament\Widgets\InventoryStats;
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

function compraPolicy(int $product, string $min = '10', string $max = '50', string $reorder = '20'): void
{
    DB::table('product_stock_policies')->insert([
        'product_id' => $product, 'warehouse_id' => (int) DB::table('warehouses')->where('code', 'BOD')->value('id'),
        'min_qty' => $min, 'max_qty' => $max, 'reorder_qty' => $reorder,
    ]);
}

it('los proveedores los gestionan compras y administración; las alertas las ven todos', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Compras));
    expect(SupplierResource::canViewAny())->toBeTrue();
    expect(SupplierResource::canCreate())->toBeTrue();
    expect(ReplenishmentResource::canViewAny())->toBeTrue();

    $this->actingAs(PanelUsers::withRole(RoleName::Almacenero));
    expect(SupplierResource::canViewAny())->toBeFalse();
    expect(ReplenishmentResource::canViewAny())->toBeTrue();

    $this->actingAs(PanelUsers::withRole(RoleName::Lectura));
    expect(SupplierResource::canCreate())->toBeFalse();
    expect(ReplenishmentResource::canViewAny())->toBeTrue();
});

it('compras crea, edita y desactiva un proveedor desde las pantallas', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Compras));

    Livewire::test(CreateSupplier::class)
        ->set('data.ruc', '20611519886')
        ->set('data.business_name', 'Importadora Lima SAC')
        ->set('data.lead_time_days', '5')
        ->call('create')
        ->assertHasNoFormErrors();

    $id = (int) DB::table('suppliers')->where('ruc', '20611519886')->value('id');
    expect(DB::table('suppliers')->where('id', $id)->value('lead_time_days'))->toBe(5);

    Livewire::test(EditSupplier::class, ['record' => $id])
        ->set('data.business_name', 'Importadora Lima Norte SAC')
        ->call('save')
        ->assertHasNoFormErrors();
    expect(DB::table('suppliers')->where('id', $id)->value('business_name'))->toBe('Importadora Lima Norte SAC');

    Livewire::test(ListSuppliers::class)->callTableAction('deactivate', $id);
    expect((bool) DB::table('suppliers')->where('id', $id)->value('is_active'))->toBeFalse();
});

it('la vista de reposición clasifica cada producto y sugiere cuánto pedir', function () {
    $actor = Fixtures::user();
    $loc = Fixtures::location('BOD');
    $low = Fixtures::product();
    $out = Fixtures::product();
    $ok = Fixtures::product();
    compraPolicy($low);
    compraPolicy($out);
    compraPolicy($ok);
    app(RegisterMovement::class)(M::purchase($low, $loc, 4, '2', $actor));
    app(RegisterMovement::class)(M::purchase($ok, $loc, 30, '2', $actor));

    $row = static fn (int $p): object => DB::table('v_replenishment')->where('product_id', $p)->firstOrFail();

    expect($row($low)->status)->toBe('LOW');
    expect((string) $row($low)->suggested_qty)->toBe('46.000');   // máximo 50 - stock 4
    expect($row($out)->status)->toBe('OUT');
    expect((string) $row($out)->suggested_qty)->toBe('50.000');
    expect($row($ok)->status)->toBe('OK');
    expect((string) $row($ok)->suggested_qty)->toBe('0');
});

it('la pantalla de reposición y el tablero abren para quien ve alertas', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Compras));

    Livewire::test(ListReplenishment::class)->assertOk();
    Livewire::test(InventoryStats::class)->assertOk()->assertSee('Valor del inventario');
    Livewire::test(CriticalStockTable::class)->assertOk();
});
