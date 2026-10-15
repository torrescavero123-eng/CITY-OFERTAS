<?php

declare(strict_types=1);

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierFormMapper;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use App\Modules\Suppliers\Application\CreateSupplier as CreateSupplierUseCase;
use App\Modules\Suppliers\Domain\SupplierException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected static bool $canCreateAnother = false;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $id = app(CreateSupplierUseCase::class)(SupplierFormMapper::toNewSupplier($data, (int) auth()->id()));
        } catch (SupplierException $e) {
            Notification::make()->title('No se pudo crear el proveedor')->body($e->getMessage())->danger()->send();
            $this->halt();

            throw $e;
        }

        return Supplier::query()->findOrFail($id);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Proveedor creado';
    }
}
