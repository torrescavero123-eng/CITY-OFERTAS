<?php

declare(strict_types=1);

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierFormMapper;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Modules\Suppliers\Application\UpdateSupplier;
use App\Modules\Suppliers\Domain\SupplierException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            app(UpdateSupplier::class)((int) $record->getKey(), SupplierFormMapper::toChanges($data, (int) auth()->id()));
        } catch (SupplierException $e) {
            Notification::make()->title('No se pudo guardar')->body($e->getMessage())->danger()->send();
            $this->halt();

            throw $e;
        }

        return $record->refresh();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Proveedor actualizado';
    }
}
