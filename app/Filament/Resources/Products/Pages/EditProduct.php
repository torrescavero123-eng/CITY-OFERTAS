<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductFormMapper;
use App\Filament\Resources\Products\ProductResource;
use App\Modules\Catalog\Application\UpdateProduct;
use App\Modules\Catalog\Domain\CatalogException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            app(UpdateProduct::class)(
                (int) $record->getKey(),
                ProductFormMapper::toChanges($data, auth()->id() === null ? null : (int) auth()->id()),
            );
        } catch (CatalogException $e) {
            // Incluye el aviso "fue modificado por otra persona" (control de versión).
            Notification::make()->title('No se pudo guardar')->body($e->getMessage())->danger()->send();
            $this->halt();

            throw $e;
        }

        return $record->refresh();
    }

    protected function afterSave(): void
    {
        $this->refreshFormData(['version', 'name']); // mantiene al día el número de versión del formulario
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Producto actualizado';
    }
}
