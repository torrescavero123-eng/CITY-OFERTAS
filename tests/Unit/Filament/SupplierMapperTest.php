<?php

declare(strict_types=1);

use App\Filament\Resources\Suppliers\SupplierFormMapper;
use App\Modules\Suppliers\Domain\SupplierException;

it('convierte el formulario de proveedor en un alta del dominio', function () {
    $s = SupplierFormMapper::toNewSupplier([
        'ruc' => ' 20611519886 ', 'business_name' => '  Importadora   Lima SAC ', 'contact_name' => '', 'phone' => '999 888 777',
        'email' => 'ventas@lima.pe', 'address' => '', 'lead_time_days' => '7',
    ], 4);

    expect($s->ruc->value)->toBe('20611519886');
    expect($s->businessName)->toBe('Importadora Lima SAC');
    expect($s->contactName)->toBeNull();
    expect($s->leadTimeDays)->toBe(7);
    expect($s->actorId)->toBe(4);
});

it('rechaza RUC inválido y plazos que no son números', function () {
    expect(fn () => SupplierFormMapper::toNewSupplier(['ruc' => '20611519887', 'business_name' => 'X SAC'], 4))->toThrow(SupplierException::class);
    expect(fn () => SupplierFormMapper::toNewSupplier(['ruc' => '20611519886', 'business_name' => 'X SAC', 'lead_time_days' => 'tres'], 4))->toThrow(SupplierException::class, 'plazo');
    expect(fn () => SupplierFormMapper::toNewSupplier(['ruc' => '', 'business_name' => 'X SAC'], 4))->toThrow(SupplierException::class);
});

it('en la edición solo viajan los cambios con valor', function () {
    $c = SupplierFormMapper::toChanges(['business_name' => 'Nuevo Nombre SAC', 'phone' => '', 'lead_time_days' => ''], 4);

    expect($c->businessName)->toBe('Nuevo Nombre SAC');
    expect($c->phone)->toBeNull();
    expect($c->leadTimeDays)->toBeNull();
    expect($c->actorId)->toBe(4);
});
