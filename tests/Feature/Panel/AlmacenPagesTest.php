<?php

declare(strict_types=1);

use App\Filament\Resources\Adjustments\AdjustmentResource;
use App\Filament\Resources\Adjustments\Pages\CreateAdjustment;
use App\Filament\Resources\Adjustments\Pages\ListAdjustments;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Receipts\Pages\CreateReceipt;
use App\Filament\Resources\Receipts\Pages\ListReceipts;
use App\Filament\Resources\Receipts\ReceiptResource;
use App\Filament\Resources\Transfers\Pages\CreateTransfer;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\TransferResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Product;
use App\Modules\Catalog\Application\LookupProduct;
use App\Modules\Iam\Domain\RoleName;
use App\Modules\Inventory\Application\GetKardex;
use App\Modules\Inventory\Application\RegisterMovement;
use App\Modules\Inventory\Domain\KardexQuery;
use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Inventory\Domain\UnitCost;
use App\Modules\Operations\Application\CreateReceiptDraft;
use App\Modules\Operations\Application\CreateTransferDraft;
use App\Modules\Operations\Application\RequestAdjustment;
use App\Modules\Operations\Domain\AdjustmentKind;
use App\Modules\Operations\Domain\DocumentType;
use App\Modules\Operations\Domain\NewAdjustment;
use App\Modules\Operations\Domain\NewReceipt;
use App\Modules\Operations\Domain\NewTransfer;
use App\Modules\Operations\Domain\ReceiptLine;
use App\Modules\Operations\Domain\TransferLine;
use App\Modules\Suppliers\Application\CreateSupplier;
use App\Modules\Suppliers\Domain\NewSupplier;
use App\Modules\Suppliers\Domain\Ruc;
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

function almBodega(): int
{
    return (int) DB::table('warehouses')->where('code', 'BOD')->value('id');
}

it('cada rol ve en el menú solo lo que le corresponde', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Almacenero));
    expect(ReceiptResource::canViewAny())->toBeTrue();
    expect(ReceiptResource::canCreate())->toBeTrue();
    expect(TransferResource::canCreate())->toBeTrue();
    expect(AdjustmentResource::canCreate())->toBeTrue();
    expect(UserResource::canViewAny())->toBeFalse();

    $this->actingAs(PanelUsers::withRole(RoleName::Compras));
    expect(ReceiptResource::canViewAny())->toBeTrue();
    expect(ReceiptResource::canCreate())->toBeFalse();
    expect(AdjustmentResource::canCreate())->toBeFalse();

    $this->actingAs(PanelUsers::withRole(RoleName::Lectura));
    expect(TransferResource::canViewAny())->toBeTrue();
    expect(TransferResource::canCreate())->toBeFalse();

    $this->actingAs(PanelUsers::withRole(RoleName::Supervisor));
    expect(ReceiptResource::canCreate())->toBeTrue();
    expect(AdjustmentResource::canViewAny())->toBeTrue();
});

it('el almacenero abre las pantallas para registrar su trabajo', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Almacenero));

    Livewire::test(CreateReceipt::class)->assertOk();
    Livewire::test(CreateTransfer::class)->assertOk();
    Livewire::test(CreateAdjustment::class)->assertOk();
    Livewire::test(ListReceipts::class)->assertOk();
});

it('solo lectura ve las listas pero no puede abrir las pantallas de registro', function () {
    $this->actingAs(PanelUsers::withRole(RoleName::Lectura));

    Livewire::test(ListReceipts::class)->assertOk();
    Livewire::test(ListTransfers::class)->assertOk();
    Livewire::test(ListAdjustments::class)->assertOk();
    Livewire::test(CreateReceipt::class)->assertForbidden();
    Livewire::test(CreateTransfer::class)->assertForbidden();
    Livewire::test(CreateAdjustment::class)->assertForbidden();
});

it('confirmar una recepción desde la lista ingresa el stock', function () {
    $worker = PanelUsers::withRole(RoleName::Almacenero);
    $supplier = app(CreateSupplier::class)(new NewSupplier(Ruc::of('20611519886'), 'Importadora Lima SAC'));
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    $draft = app(CreateReceiptDraft::class)(new NewReceipt(
        $supplier, almBodega(), DocumentType::Invoice, 'F001-777', now('America/Lima')->format('Y-m-d'), $worker->id,
        [new ReceiptLine($product, $loc, Quantity::of('12'), UnitCost::of('3'))],
    ));
    $this->actingAs($worker);

    Livewire::test(ListReceipts::class)
        ->assertCanSeeTableRecords([$draft['id']])
        ->callTableAction('confirm', $draft['id']);

    expect(Fixtures::balance($product, $loc))->toBe('12.000');
    expect(DB::table('purchase_receipts')->where('id', $draft['id'])->value('status'))->toBe('CONFIRMED');
});

it('completar una transferencia desde la lista mueve el stock', function () {
    $worker = PanelUsers::withRole(RoleName::Almacenero);
    $product = Fixtures::product();
    $from = Fixtures::location('BOD');
    $to = Fixtures::location('PIS');
    app(RegisterMovement::class)(M::purchase($product, $from, 20, '1', $worker->id));
    $draft = app(CreateTransferDraft::class)(new NewTransfer($worker->id, [new TransferLine($product, $from, $to, Quantity::of('5'))]));
    $this->actingAs($worker);

    Livewire::test(ListTransfers::class)->callTableAction('complete', $draft['id']);

    expect(Fixtures::balance($product, $from))->toBe('15.000');
    expect(Fixtures::balance($product, $to))->toBe('5.000');
});

it('el supervisor aprueba la merma que pidió el almacenero; el almacenero no ve el botón', function () {
    $worker = PanelUsers::withRole(RoleName::Almacenero);
    $supervisor = PanelUsers::withRole(RoleName::Supervisor);
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    app(RegisterMovement::class)(M::purchase($product, $loc, 10, '4', $supervisor->id));
    $reason = (int) DB::table('movement_reasons')->where('code', 'MERMA_DANIO')->value('id');
    $request = app(RequestAdjustment::class)(new NewAdjustment(AdjustmentKind::WriteOff, $product, $loc, Quantity::of('3'), $reason, 'Se mojaron con la lluvia', $worker->id));

    $this->actingAs($worker);
    Livewire::test(ListAdjustments::class)->assertTableActionHidden('approve', $request['id']);

    $this->actingAs($supervisor);
    Livewire::test(ListAdjustments::class)
        ->assertTableActionVisible('approve', $request['id'])
        ->callTableAction('approve', $request['id'], data: ['notes' => 'Visto bueno']);

    expect(Fixtures::balance($product, $loc))->toBe('7.000');
    expect(DB::table('stock_adjustment_requests')->where('id', $request['id'])->value('status'))->toBe('APPROVED');
});

it('el kardex de un producto muestra el stock actual y los movimientos', function () {
    $admin = PanelUsers::withRole(RoleName::Admin);
    $product = Fixtures::product();
    $loc = Fixtures::location('BOD');
    app(RegisterMovement::class)(M::purchase($product, $loc, 8, '2', $admin->id));
    $sku = (string) DB::table('products')->where('id', $product)->value('sku');

    $html = view('filament.kardex', [
        'lookup' => app(LookupProduct::class)($sku),
        'kardex' => app(GetKardex::class)(new KardexQuery($product, limit: 50)),
        'product' => Product::query()->findOrFail($product),
    ])->render();

    expect($html)->toContain($sku)->toContain('Compra')->toContain('BOD-')->toContain('Saldo');

    $this->actingAs($admin);
    Livewire::test(ListProducts::class)->assertTableActionExists('kardex');
});
