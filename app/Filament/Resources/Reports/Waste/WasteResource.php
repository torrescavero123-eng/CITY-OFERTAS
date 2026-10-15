<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Waste;

use App\Filament\Resources\Reports\Waste\Pages\ListWaste;
use App\Models\WasteRow;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class WasteResource extends Resource
{
    protected static ?string $model = WasteRow::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trash';

    protected static string|UnitEnum|null $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Mermas y ajustes negativos';

    protected static ?string $modelLabel = 'movimiento';

    protected static ?string $pluralModelLabel = 'mermas y ajustes negativos';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        $money = static fn ($state): string => 'S/ '.number_format((float) $state, 2, '.', ',');
        $qty = static fn ($state): string => rtrim(rtrim(number_format((float) $state, 3, '.', ','), '0'), '.') ?: '0';

        return $table
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i', 'America/Lima')->sortable(),
                TextColumn::make('type')->label('Tipo')->badge()
                    ->formatStateUsing(static fn (string $state): string => $state === 'MERMA' ? 'Merma' : 'Ajuste (−)')
                    ->color(static fn (string $state): string => $state === 'MERMA' ? 'danger' : 'warning'),
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('product_name')->label('Producto')->searchable()->wrap(),
                TextColumn::make('location_code')->label('Ubicación')->toggleable(),
                TextColumn::make('reason_name')->label('Motivo')->wrap(),
                TextColumn::make('quantity')->label('Cantidad')->alignEnd()->formatStateUsing($qty)
                    ->summarize(Sum::make()->label('Unidades')->formatStateUsing($qty)),
                TextColumn::make('value')->label('Valor (costo)')->alignEnd()->sortable()->formatStateUsing($money)
                    ->summarize(Sum::make()->label('Valor total')->formatStateUsing($money)),
                TextColumn::make('user_name')->label('Registró')->toggleable(),
                TextColumn::make('approver_name')->label('Aprobó')->placeholder('—')->toggleable(),
                TextColumn::make('notes')->label('Explicación')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(['MERMA' => 'Merma', 'AJUSTE_NEGATIVO' => 'Ajuste (−)']),
                SelectFilter::make('reason_name')->label('Motivo')
                    ->options(static fn (): array => DB::table('movement_reasons')->whereIn('movement_type', ['MERMA', 'AJUSTE_NEGATIVO'])->orderBy('name')->pluck('name', 'name')->map(static fn ($n): string => (string) $n)->all()),
                Filter::make('period')->label('Período')
                    ->schema([DatePicker::make('from')->label('Desde'), DatePicker::make('until')->label('Hasta')])
                    ->query(static fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, static fn (Builder $q, $d): Builder => $q->where('created_at', '>=', $d.' 00:00:00-05'))
                        ->when($data['until'] ?? null, static fn (Builder $q, $d): Builder => $q->where('created_at', '<=', $d.' 23:59:59-05'))),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay mermas ni ajustes negativos');
    }

    public static function getPages(): array
    {
        return ['index' => ListWaste::route('/')];
    }
}
