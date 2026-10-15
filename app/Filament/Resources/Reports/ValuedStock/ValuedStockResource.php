<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\ValuedStock;

use App\Filament\Resources\Reports\ValuedStock\Pages\ListValuedStock;
use App\Models\ValuedStockRow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ValuedStockResource extends Resource
{
    protected static ?string $model = ValuedStockRow::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Inventario valorizado';

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'inventario valorizado';

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
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('product_name')->label('Producto')->searchable()->wrap(),
                TextColumn::make('category_name')->label('Categoría')->toggleable(),
                TextColumn::make('warehouse_name')->label('Almacén'),
                TextColumn::make('qty')->label('Unidades')->alignEnd()->sortable()->formatStateUsing($qty)
                    ->summarize(Sum::make()->label('Total')->formatStateUsing($qty)),
                TextColumn::make('cost_avg')->label('Costo prom.')->alignEnd()->formatStateUsing($money),
                TextColumn::make('value')->label('Valor')->alignEnd()->sortable()->formatStateUsing($money)
                    ->summarize(Sum::make()->label('Valor total')->formatStateUsing($money)),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')->label('Almacén')
                    ->options(static fn (): array => DB::table('warehouses')->orderBy('name')->pluck('name', 'id')->map(static fn ($n): string => (string) $n)->all()),
                SelectFilter::make('category_name')->label('Categoría')
                    ->options(static fn (): array => DB::table('categories')->orderBy('name')->pluck('name', 'name')->map(static fn ($n): string => (string) $n)->all()),
            ])
            ->defaultSort('value', 'desc')
            ->emptyStateHeading('No hay stock valorizado todavía');
    }

    public static function getPages(): array
    {
        return ['index' => ListValuedStock::route('/')];
    }
}
