<?php

declare(strict_types=1);

namespace App\Filament\Resources\Counts\Pages;

use App\Filament\Resources\Counts\CountResource;
use App\Filament\Resources\Products\CatalogOptions;
use App\Modules\Operations\Application\StartCount;
use App\Modules\Operations\Domain\CountScopeType;
use App\Modules\Operations\Domain\NewCount;
use App\Modules\Operations\Domain\OperationsException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class ListCounts extends ListRecords
{
    protected static string $resource = CountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')->label('Iniciar conteo')->icon('heroicon-o-play')
                ->visible(static fn (): bool => auth()->user()?->can('counts.close') ?? false)
                ->modalHeading('Iniciar conteo de inventario')
                ->modalDescription('Se fija el stock esperado y se CONGELAN las ubicaciones del alcance: no admitirán movimientos hasta cerrar o anular el conteo.')
                ->schema([
                    Select::make('warehouse_id')->label('Almacén')->options(fn (): array => CatalogOptions::warehouses())->required(),
                    Select::make('scope_type')->label('Qué contar')->required()->live()
                        ->options([
                            'ZONE' => 'Una zona (por ejemplo Z1)',
                            'LOCATION' => 'Una ubicación (por ejemplo BOD-Z1-P03-A02-N2)',
                            'CATEGORY' => 'Una categoría (con sus subcategorías)',
                            'ABC_A' => 'Productos de clase A (los que más se venden)',
                            'FULL' => 'Todo el almacén',
                        ]),
                    TextInput::make('scope_value')->label('Zona, código de ubicación o número de categoría')->maxLength(40)
                        ->visible(static fn (Get $get): bool => in_array((string) $get('scope_type'), ['ZONE', 'LOCATION', 'CATEGORY'], true))
                        ->required(static fn (Get $get): bool => in_array((string) $get('scope_type'), ['ZONE', 'LOCATION', 'CATEGORY'], true))
                        ->helperText('Zona: "Z1" o solo "1". Categoría: el número que aparece en la lista de categorías.'),
                ])
                ->action(static function (array $data): void {
                    try {
                        $scope = CountScopeType::tryFrom((string) ($data['scope_type'] ?? '')) ?? throw OperationsException::invalid('Seleccione qué contar.');
                        $result = app(StartCount::class)(new NewCount(
                            (int) $data['warehouse_id'], $scope, ($data['scope_value'] ?? null) === null ? null : (string) $data['scope_value'], (int) auth()->id(),
                        ));
                        Notification::make()->title('Conteo iniciado: '.$result['code'])
                            ->body("Ubicaciones congeladas: {$result['locations']}. Líneas a contar: {$result['lines']}.")->success()->send();
                    } catch (OperationsException $e) {
                        Notification::make()->title('No se pudo iniciar')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
