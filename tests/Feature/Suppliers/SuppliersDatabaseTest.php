<?php

declare(strict_types=1);

use App\Modules\Suppliers\Application\ChangeSupplierState;
use App\Modules\Suppliers\Application\CreateSupplier;
use App\Modules\Suppliers\Application\LinkProductToSupplier;
use App\Modules\Suppliers\Application\UpdateSupplier;
use App\Modules\Suppliers\Domain\NewSupplier;
use App\Modules\Suppliers\Domain\Ruc;
use App\Modules\Suppliers\Domain\SupplierChanges;
use App\Modules\Suppliers\Domain\SupplierException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;

it('crea el proveedor, rechaza el RUC repetido y audita', function () {
    $actor = Fixtures::user();
    $create = app(CreateSupplier::class);

    $id = $create(new NewSupplier(Ruc::of('20100070970'), 'Supermercados Peruanos SA', 'Ana', '999 888 777', 'ventas@sp.pe', null, 3, $actor));

    $row = DB::table('suppliers')->where('id', $id)->first();
    expect(trim((string) $row->ruc))->toBe('20100070970');
    expect((int) $row->lead_time_days)->toBe(3);
    expect(fn () => $create(new NewSupplier(Ruc::of('20100070970'), 'Otro nombre')))->toThrow(SupplierException::class, 'Ya existe');
    expect(DB::table('audit_logs')->where('action', 'supplier.created')->where('entity_id', $id)->count())->toBe(1);
});

it('actualiza y desactiva sin borrar el proveedor', function () {
    $actor = Fixtures::user();
    $id = app(CreateSupplier::class)(new NewSupplier(Ruc::of('20100070970'), 'Supermercados Peruanos SA', null, '999 888 777'));

    app(UpdateSupplier::class)($id, new SupplierChanges(phone: '999 111 222', actorId: $actor));
    app(ChangeSupplierState::class)->deactivate($id, $actor);

    $row = DB::table('suppliers')->where('id', $id)->first();
    expect($row->phone)->toBe('999 111 222');
    expect((bool) $row->is_active)->toBeFalse();
    $audit = DB::table('audit_logs')->where('action', 'supplier.updated')->where('entity_id', $id)->first();
    expect(json_decode((string) $audit->old_values, true))->toBe(['phone' => '999 888 777']);
});

it('vincula productos, deja un solo proveedor preferido y conserva el último costo', function () {
    $product = Fixtures::product();
    $create = app(CreateSupplier::class);
    $a = $create(new NewSupplier(Ruc::of('20611519886'), 'Proveedor A SAC'));
    $b = $create(new NewSupplier(Ruc::of('20100070970'), 'Proveedor B SAC'));
    $link = app(LinkProductToSupplier::class);

    $link($product, $a, 'A-001', '2.5', true);
    $link($product, $b, null, '2.4', true);
    $link($product, $b, 'B-777', null, true); // actualizar sin costo no borra el último costo

    $rows = DB::table('product_suppliers')->where('product_id', $product)->get()->keyBy('supplier_id');
    expect((bool) $rows[$a]->is_preferred)->toBeFalse();
    expect((bool) $rows[$b]->is_preferred)->toBeTrue();
    expect($rows[$b]->supplier_sku)->toBe('B-777');
    expect((string) $rows[$b]->last_cost)->toBe('2.4000');
    expect(DB::table('product_suppliers')->where('product_id', $product)->where('is_preferred', true)->count())->toBe(1);
});

it('no vincula con proveedores inactivos ni productos inexistentes', function () {
    $product = Fixtures::product();
    $id = app(CreateSupplier::class)(new NewSupplier(Ruc::of('20100070970'), 'Supermercados Peruanos SA'));
    app(ChangeSupplierState::class)->deactivate($id);
    $link = app(LinkProductToSupplier::class);

    expect(fn () => $link($product, $id))->toThrow(SupplierException::class, 'desactivado');

    app(ChangeSupplierState::class)->activate($id);
    expect(fn () => $link(99999999, $id))->toThrow(SupplierException::class, 'no existe');
});
