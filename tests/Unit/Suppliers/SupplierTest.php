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
use Tests\Support\PassthroughTransaction;
use Tests\Support\RecordingAuditTrail;
use Tests\Support\Suppliers\InMemorySupplierRepository;

function supplierKit(): array
{
    $repo = new InMemorySupplierRepository;
    $audit = new RecordingAuditTrail;
    $tx = new PassthroughTransaction;

    return [
        $repo, $audit,
        new CreateSupplier($repo, $tx, $audit),
        new UpdateSupplier($repo, $tx, $audit),
        new ChangeSupplierState($repo, $tx, $audit),
        new LinkProductToSupplier($repo, $tx, $audit),
    ];
}

it('valida el RUC con su dígito verificador', function () {
    expect(Ruc::of('20611519886')->value)->toBe('20611519886'); // el de CITY OFERTAS
    expect(fn () => Ruc::of('20611519887'))->toThrow(SupplierException::class, 'dígito verificador');
    expect(fn () => Ruc::of('2061151988'))->toThrow(SupplierException::class, '11 dígitos');
    expect(fn () => Ruc::of('30611519886'))->toThrow(SupplierException::class, 'empezar');
    expect(fn () => Ruc::of('2061151988A'))->toThrow(SupplierException::class, '11 dígitos');
});

it('normaliza y valida los datos del proveedor', function () {
    $s = new NewSupplier(Ruc::of('20611519886'), '  Importadora   Lima  SAC ', ' Ana ', '999 888 777', 'VENTAS@Lima.PE', '', 5);

    expect($s->businessName)->toBe('Importadora Lima SAC');
    expect($s->contactName)->toBe('Ana');
    expect($s->email)->toBe('ventas@lima.pe');
    expect($s->address)->toBeNull();

    expect(fn () => new NewSupplier(Ruc::of('20611519886'), 'X'))->toThrow(SupplierException::class, 'razón social');
    expect(fn () => new NewSupplier(Ruc::of('20611519886'), 'Lima SAC', null, 'abc'))->toThrow(SupplierException::class, 'teléfono');
    expect(fn () => new NewSupplier(Ruc::of('20611519886'), 'Lima SAC', null, null, 'no-es-correo'))->toThrow(SupplierException::class, 'correo');
    expect(fn () => new NewSupplier(Ruc::of('20611519886'), 'Lima SAC', null, null, null, null, 400))->toThrow(SupplierException::class, 'plazo');
});

it('crea un proveedor y rechaza el RUC repetido', function () {
    [$repo, $audit, $create] = supplierKit();

    $id = $create(new NewSupplier(Ruc::of('20611519886'), 'Importadora Lima SAC', null, null, null, null, null, 7));

    expect($repo->rows[$id]['active'])->toBeTrue();
    expect($audit->entries[0]->action)->toBe('supplier.created');
    expect(fn () => $create(new NewSupplier(Ruc::of('20611519886'), 'Otra razón social')))->toThrow(SupplierException::class, 'Ya existe');
});

it('actualiza solo lo que cambió y audita antes/después', function () {
    [$repo, $audit, $create, $update] = supplierKit();
    $id = $create(new NewSupplier(Ruc::of('20611519886'), 'Importadora Lima SAC', null, '999 888 777'));

    $update($id, new SupplierChanges(phone: '999 111 222', leadTimeDays: 7));
    $update($id, new SupplierChanges(phone: '999 111 222')); // sin cambios reales

    expect($repo->rows[$id]['data']['phone'])->toBe('999 111 222');
    expect($repo->rows[$id]['data']['lead_time_days'])->toBe(7);
    expect($audit->entries)->toHaveCount(2); // creación + una sola actualización
    expect($audit->entries[1]->oldValues)->toBe(['phone' => '999 888 777', 'lead_time_days' => null]);
    expect(fn () => $update(404, new SupplierChanges(phone: '999 111 222')))->toThrow(SupplierException::class, 'no existe');
});

it('activar y desactivar son idempotentes', function () {
    [$repo, $audit, $create, , $state] = supplierKit();
    $id = $create(new NewSupplier(Ruc::of('20611519886'), 'Importadora Lima SAC'));

    $state->deactivate($id);
    $state->deactivate($id);
    expect($repo->rows[$id]['active'])->toBeFalse();
    $state->activate($id);
    expect($repo->rows[$id]['active'])->toBeTrue();
    expect($audit->entries)->toHaveCount(3);
});

it('vincula productos con un solo proveedor preferido y rechaza proveedores inactivos', function () {
    [$repo, , $create, , $state, $link] = supplierKit();
    $a = $create(new NewSupplier(Ruc::of('20611519886'), 'Proveedor A SAC'));
    $b = $create(new NewSupplier(Ruc::of('20100070970'), 'Proveedor B SAC'));

    $link(10, $a, 'A-001', '2.5', true);
    $link(10, $b, null, '2.4', true);

    expect($repo->links["10:{$a}"]['preferred'])->toBeFalse();
    expect($repo->links["10:{$b}"]['preferred'])->toBeTrue();
    expect($repo->links["10:{$a}"]['cost'])->toBe('2.5000');

    $state->deactivate($b);
    expect(fn () => $link(10, $b))->toThrow(SupplierException::class, 'desactivado');
    expect(fn () => $link(999, $a))->toThrow(SupplierException::class, 'no existe');
    expect(fn () => $link(10, $a, null, 'abc'))->toThrow(SupplierException::class, 'costo');
});
