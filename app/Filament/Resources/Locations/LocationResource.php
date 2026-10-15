<?php

declare(strict_types=1);

namespace App\Filament\Resources\Locations;

use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Models\Location;
use App\Modules\Locations\Application\ChangeLocationState;
use App\Modules\Locations\Domain\LocationException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = 'Almacén';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Ubicaciones';

    protected static ?string $modelLabel = 'ubicación';

    protected static ?string $pluralModelLabel = 'ubicaciones';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema; // las ubicaciones se crean en lote con la acción "Generar ubicaciones"
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable()->copyable(),
                TextColumn::make('warehouse_name')->label('Almacén'),
                TextColumn::make('zone')->label('Zona')->sortable(),
                TextColumn::make('aisle')->label('Pasillo'),
                TextColumn::make('rack')->label('Anaquel'),
                TextColumn::make('level')->label('Nivel'),
                TextColumn::make('stock_total')->label('Unidades')->alignEnd()->sortable()
                    ->formatStateUsing(static fn ($state): string => rtrim(rtrim(number_format((float) $state, 3, '.', ','), '0'), '.') ?: '0'),
                TextColumn::make('is_active')->label('Estado')->badge()
                    ->formatStateUsing(static fn ($state, Location $record): string => ! $record->is_active ? 'Desactivada' : ($record->is_frozen ? 'En conteo' : ($record->is_staging ? 'Recepción' : 'Activa')))
                    ->color(static fn ($state, Location $record): string => ! $record->is_active ? 'gray' : ($record->is_frozen ? 'warning' : 'success')),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')->label('Almacén')
                    ->options(static fn (): array => DB::table('warehouses')->orderBy('name')->pluck('name', 'id')->map(static fn ($n): string => (string) $n)->all()),
                TernaryFilter::make('is_active')->label('Activa'),
                TernaryFilter::make('is_frozen')->label('En conteo'),
                Filter::make('with_stock')->label('Con stock')
                    ->query(static fn (Builder $query): Builder => $query->whereRaw('EXISTS (SELECT 1 FROM stock_balances sb WHERE sb.location_id = locations.id AND sb.quantity > 0)')),
            ])
            ->recordActions([
                Action::make('deactivate')->label('Desactivar')->icon('heroicon-o-no-symbol')->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Solo se pueden desactivar ubicaciones vacías, que no estén en conteo ni sean de recepción.')
                    ->visible(static fn (Location $record): bool => $record->is_active && ! $record->is_staging && (auth()->user()?->can('locations.manage') ?? false))
                    ->action(static fn (Location $record) => self::run(static fn () => app(ChangeLocationState::class)->deactivate((int) $record->id, (int) auth()->id()), 'Ubicación desactivada')),
                Action::make('activate')->label('Reactivar')->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->visible(static fn (Location $record): bool => ! $record->is_active && (auth()->user()?->can('locations.manage') ?? false))
                    ->action(static fn (Location $record) => self::run(static fn () => app(ChangeLocationState::class)->activate((int) $record->id, (int) auth()->id()), 'Ubicación reactivada')),
                Action::make('unfreeze')->label('Descongelar')->icon('heroicon-o-lock-open')->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Úselo solo si un conteo se quedó sin cerrar por error: mientras la ubicación está congelada no admite movimientos.')
                    ->visible(static fn (Location $record): bool => $record->is_frozen && (auth()->user()?->can('counts.close') ?? false))
                    ->action(static fn (Location $record) => self::run(static fn () => app(ChangeLocationState::class)->unfreeze((int) $record->id, (int) auth()->id()), 'Ubicación descongelada')),
            ])
            ->defaultSort('code')
            ->emptyStateHeading('Aún no hay ubicaciones')
            ->emptyStateDescription('Cree anaqueles en lote con "Generar ubicaciones" (por ejemplo, pasillos 1 al 10 × anaqueles 1 al 8 × niveles 1 al 4).');
    }

    public static function run(callable $operation, string $successTitle): void
    {
        try {
            $operation();
            Notification::make()->title($successTitle)->success()->send();
        } catch (LocationException $e) {
            Notification::make()->title('No se pudo completar')->body($e->getMessage())->danger()->send();
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select('locations.*')
            ->addSelect([
                'warehouse_name' => DB::table('warehouses')->select('name')->whereColumn('warehouses.id', 'locations.warehouse_id'),
                'stock_total' => DB::table('stock_balances')->selectRaw('COALESCE(SUM(quantity), 0)')->whereColumn('stock_balances.location_id', 'locations.id'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLocations::route('/')];
    }
}
