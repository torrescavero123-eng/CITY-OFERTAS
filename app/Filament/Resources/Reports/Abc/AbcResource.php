<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Abc;

use App\Filament\Resources\Reports\Abc\Pages\ListAbc;
use App\Models\AbcRow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AbcResource extends Resource
{
    public const CLASS_LABELS = ['A' => 'A · los que más rotan', 'B' => 'B · rotación media', 'C' => 'C · poca rotación', 'SIN_VENTAS' => 'Sin ventas en 90 días'];

    protected static ?string $model = AbcRow::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Rotación y clase ABC';

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'rotación y clasificación ABC (90 días)';

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
                TextColumn::make('abc_class')->label('Clase')->badge()
                    ->formatStateUsing(static fn (string $state): string => $state === 'SIN_VENTAS' ? 'Sin ventas' : $state)
                    ->color(static fn (string $state): string => ['A' => 'success', 'B' => 'info', 'C' => 'warning', 'SIN_VENTAS' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('product_name')->label('Producto')->searchable()->wrap(),
                TextColumn::make('category_name')->label('Categoría')->toggleable(),
                TextColumn::make('qty_90d')->label('Vendido 90 d')->alignEnd()->sortable()->formatStateUsing($qty),
                TextColumn::make('value_90d')->label('Valor vendido (costo)')->alignEnd()->sortable()->formatStateUsing($money),
                TextColumn::make('share_pct')->label('% del total')->alignEnd()->placeholder('—')->formatStateUsing(static fn ($state): string => number_format((float) $state, 2).' %'),
                TextColumn::make('stock')->label('Stock')->alignEnd()->sortable()->formatStateUsing($qty),
                TextColumn::make('cover_days')->label('Cobertura (días)')->alignEnd()->sortable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('abc_class')->label('Clase')->options(self::CLASS_LABELS),
                Filter::make('dead_stock')->label('Con stock pero sin ventas (capital parado)')
                    ->query(static fn (Builder $query): Builder => $query->where('abc_class', 'SIN_VENTAS')->where('stock', '>', 0)),
            ])
            ->defaultSort('value_90d', 'desc')
            ->emptyStateHeading('Aún no hay ventas registradas en los últimos 90 días');
    }

    public static function getPages(): array
    {
        return ['index' => ListAbc::route('/')];
    }
}
