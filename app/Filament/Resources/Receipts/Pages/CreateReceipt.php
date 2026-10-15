<?php

declare(strict_types=1);

namespace App\Filament\Resources\Receipts\Pages;

use App\Filament\Resources\Receipts\ReceiptFormMapper;
use App\Filament\Resources\Receipts\ReceiptResource;
use App\Models\PurchaseReceipt;
use App\Modules\Operations\Application\CreateReceiptDraft;
use App\Modules\Operations\Domain\OperationsException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReceipt extends CreateRecord
{
    protected static string $resource = ReceiptResource::class;

    protected static bool $canCreateAnother = false;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $created = app(CreateReceiptDraft::class)(ReceiptFormMapper::toNewReceipt($data, (int) auth()->id()));
        } catch (OperationsException $e) {
            Notification::make()->title('No se pudo guardar la recepción')->body($e->getMessage())->danger()->send();
            $this->halt();

            throw $e;
        }

        return PurchaseReceipt::query()->findOrFail($created['id']);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Recepción guardada como borrador. Confírmela desde la lista para ingresar el stock.';
    }

    protected function getRedirectUrl(): string
    {
        return ReceiptResource::getUrl('index');
    }
}
