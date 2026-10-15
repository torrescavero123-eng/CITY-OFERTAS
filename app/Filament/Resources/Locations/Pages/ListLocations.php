<?php

declare(strict_types=1);

namespace App\Filament\Resources\Locations\Pages;

use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\Products\CatalogOptions;
use App\Modules\Locations\Application\GenerateLocations;
use App\Modules\Locations\Domain\LocationBatch;
use App\Modules\Locations\Domain\LocationException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label('Generar ubicaciones')->icon('heroicon-o-squares-plus')
                ->visible(static fn (): bool => auth()->user()?->can('locations.manage') ?? false)
                ->modalHeading('Generar ubicaciones en lote')
                ->modalDescription('Crea zona × pasillos × anaqueles × niveles (máximo 2000 por lote). Si repite el lote, solo se crean las que faltan. Código resultante: BOD-Z1-P03-A02-N2.')
                ->schema([
                    Select::make('warehouse_id')->label('Almacén')->options(fn (): array => CatalogOptions::warehouses())->required(),
                    TextInput::make('zone')->label('Zona (número)')->numeric()->minValue(1)->maxValue(99)->default(1)->required(),
                    TextInput::make('aisle_from')->label('Pasillo desde')->numeric()->minValue(1)->maxValue(99)->default(1)->required(),
                    TextInput::make('aisle_to')->label('Pasillo hasta')->numeric()->minValue(1)->maxValue(99)->default(1)->required(),
                    TextInput::make('rack_from')->label('Anaquel desde')->numeric()->minValue(1)->maxValue(99)->default(1)->required(),
                    TextInput::make('rack_to')->label('Anaquel hasta')->numeric()->minValue(1)->maxValue(99)->default(1)->required(),
                    TextInput::make('level_from')->label('Nivel desde')->numeric()->minValue(1)->maxValue(9)->default(1)->required(),
                    TextInput::make('level_to')->label('Nivel hasta')->numeric()->minValue(1)->maxValue(9)->default(1)->required(),
                    TextInput::make('max_capacity')->label('Capacidad máxima por ubicación (opcional)')->numeric()->minValue(0.001),
                ])
                ->action(static function (array $data): void {
                    try {
                        $result = app(GenerateLocations::class)(new LocationBatch(
                            (int) $data['warehouse_id'], (int) $data['zone'],
                            (int) $data['aisle_from'], (int) $data['aisle_to'],
                            (int) $data['rack_from'], (int) $data['rack_to'],
                            (int) $data['level_from'], (int) $data['level_to'],
                            (int) auth()->id(),
                            ($data['max_capacity'] ?? null) === null || $data['max_capacity'] === '' ? null : (string) $data['max_capacity'],
                        ));
                        Notification::make()->title('Ubicaciones generadas')
                            ->body("Creadas: {$result['created']}. Ya existían: {$result['skipped']}.")->success()->send();
                    } catch (LocationException $e) {
                        Notification::make()->title('No se pudo generar')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
