<?php

declare(strict_types=1);

namespace App\Filament\Resources\Replenishment;

use App\Filament\Resources\Replenishment\Pages\ListReplenishment;
use App\Models\ReplenishmentSuggestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ReplenishmentResource extends Resource
{
    public const STATUS_LABELS = ['OUT' => 'Agotado', 'LOW' => 'Bajo el mínimo', 'NEAR' => 'Cerca del mínimo', 'OVER' => 'Sobrestock', 'OK' => 'Normal'];

    protected static ?string $model = ReplenishmentSuggestion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|UnitEnum|null $navigationGroup = 'Compras';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Reposición y alertas';

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'reposición y alertas de stock';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        $qty = static fn ($state): string => $state === null ? '—' : (rtrim(rtrim(number_format((float) $state, 3, '.', ','), '0'), '.') ?: '0');

        return $table
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('product_name')->label('Producto')->searchable()->wrap(),
                TextColumn::make('warehouse_name')->label('Almacén')->toggleable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(static fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(static fn (string $state): string => ['OUT' => 'danger', 'LOW' => 'warning', 'NEAR' => 'info', 'OVER' => 'gray', 'OK' => 'success'][$state] ?? 'gray'),
                TextColumn::make('stock')->label('Stock')->alignEnd()->sortable()->formatStateUsing($qty),
                TextColumn::make('min_qty')->label('Mínimo')->alignEnd()->formatStateUsing($qty),
                TextColumn::make('max_qty')->label('Máximo')->alignEnd()->formatStateUsing($qty),
                TextColumn::make('suggested_qty')->label('Pedir (sugerido)')->alignEnd()->sortable()->weight('bold')->formatStateUsing($qty),
                TextColumn::make('sold_30d')->label('Vendido 30 d')->alignEnd()->formatStateUsing($qty)->toggleable(),
                TextColumn::make('cover')->label('Cobertura (días)')->alignEnd()->toggleable()
                    ->state(static fn (ReplenishmentSuggestion $r): string => (float) $r->getAttribute('sold_30d') > 0
                        ? (string) (int) round((float) $r->getAttribute('stock') / ((float) $r->getAttribute('sold_30d') / 30))
                        : '—'),
                TextColumn::make('supplier_name')->label('Proveedor preferido')->placeholder('Sin proveedor')->toggleable(),
                TextColumn::make('lead_time_days')->label('Plazo (días)')->alignEnd()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('attention')->label('Solo lo que requiere atención')->default()
                    ->query(static fn (Builder $query): Builder => $query->whereIn('status', ['OUT', 'LOW', 'NEAR'])),
                SelectFilter::make('status')->label('Estado')->options(self::STATUS_LABELS),
                SelectFilter::make('warehouse_id')->label('Almacén')
                    ->options(static fn (): array => DB::table('warehouses')->orderBy('name')->pluck('name', 'id')->map(static fn ($n): string => (string) $n)->all()),
            ])
            ->defaultSort('suggested_qty', 'desc')
            ->emptyStateHeading('Todo en orden')
            ->emptyStateDescription('Ningún producto con política de stock está agotado, bajo el mínimo o cerca de él.');
    }

    public static function getPages(): array
    {
        return ['index' => ListReplenishment::route('/')];
    }
}
