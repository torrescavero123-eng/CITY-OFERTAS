<?php

declare(strict_types=1);

namespace App\Filament\Resources\Adjustments\Pages;

use App\Filament\Resources\Adjustments\AdjustmentFormMapper;
use App\Filament\Resources\Adjustments\AdjustmentResource;
use App\Models\StockAdjustmentRequest;
use App\Modules\Operations\Application\RequestAdjustment;
use App\Modules\Operations\Domain\OperationsException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAdjustment extends CreateRecord
{
    protected static string $resource = AdjustmentResource::class;

    protected static bool $canCreateAnother = false;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $created = app(RequestAdjustment::class)(AdjustmentFormMapper::toNewAdjustment($data, (int) auth()->id()));
        } catch (OperationsException $e) {
            Notification::make()->title('No se pudo enviar la solicitud')->body($e->getMessage())->danger()->send();
            $this->halt();

            throw $e;
        }

        return StockAdjustmentRequest::query()->findOrFail($created['id']);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Solicitud enviada. Un supervisor debe aprobarla para que el stock cambie.';
    }

    protected function getRedirectUrl(): string
    {
        return AdjustmentResource::getUrl('index');
    }
}
