<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Resources\Transfers\TransferFormMapper;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\TransferOrder;
use App\Modules\Operations\Application\CreateTransferDraft;
use App\Modules\Operations\Domain\OperationsException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTransfer extends CreateRecord
{
    protected static string $resource = TransferResource::class;

    protected static bool $canCreateAnother = false;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $created = app(CreateTransferDraft::class)(TransferFormMapper::toNewTransfer($data, (int) auth()->id()));
        } catch (OperationsException $e) {
            Notification::make()->title('No se pudo guardar la transferencia')->body($e->getMessage())->danger()->send();
            $this->halt();

            throw $e;
        }

        return TransferOrder::query()->findOrFail($created['id']);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Transferencia guardada como borrador. Complétela desde la lista para mover el stock.';
    }

    protected function getRedirectUrl(): string
    {
        return TransferResource::getUrl('index');
    }
}
