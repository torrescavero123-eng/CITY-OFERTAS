<?php

declare(strict_types=1);

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Product;
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

it('el administrador ve el listado de productos', function () {
    $admin = PanelUsers::withRole(RoleName::Admin);
    $productId = Fixtures::product();
    $loc = Fixtures::location('BOD');
    app(RegisterMovement::class)(M::purchase($productId, $loc, 7, '2', $admin->id));

    $this->actingAs($admin);

    Livewire::test(ListProducts::class)
        ->assertOk()
        ->assertCanSeeTableRecords(Product::query()->whereKey($productId)->get());
});

it('el almacenero ve los productos pero no puede crearlos', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Almacenero));

    Livewire::test(ListProducts::class)->assertOk();
    Livewire::test(CreateProduct::class)->assertForbidden();
});

it('solo el administrador entra a la lista de usuarios', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Supervisor));
    Livewire::test(ListUsers::class)->assertForbidden();

    $this->actingAs(PanelUsers::withRole(RoleName::Admin));
    Livewire::test(ListUsers::class)->assertOk();
});

it('crear un producto desde el formulario pasa por el caso de uso y genera el SKU', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Supervisor));

    Livewire::test(CreateProduct::class)
        ->set('data.name', 'Cuaderno A4 100 hojas')
        ->set('data.category_id', (int) DB::table('categories')->whereNull('parent_id')->where('code', 'LIB')->value('id'))
        ->set('data.base_unit_id', (int) DB::table('units')->where('code', 'UND')->value('id'))
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('name', 'Cuaderno A4 100 hojas')->firstOrFail();
    expect($product->sku)->toBe('LIB-000001');
    expect(DB::table('audit_logs')->where('action', 'product.created')->where('entity_id', $product->id)->count())->toBe(1);
});
