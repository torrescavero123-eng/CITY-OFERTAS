<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductFormMapper;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Modules\Catalog\Application\CreateProduct as CreateProductUseCase;
use App\Modules\Catalog\Domain\CatalogException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected static bool $canCreateAnother = false;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $created = app(CreateProductUseCase::class)(
                ProductFormMapper::toNewProduct($data, auth()->id() === null ? null : (int) auth()->id()),
            );
        } catch (CatalogException $e) {
            Notification::make()->title('No se pudo crear el producto')->body($e->getMessage())->danger()->send();
            $this->halt();

            throw $e;
        }

        return Product::query()->findOrFail($created['id']);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Producto creado';
    }
}
