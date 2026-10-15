<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ReplenishmentSuggestion;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CriticalStockTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('alerts.view') ?? false;
    }

    public function table(Table $table): Table
    {
        $qty = static fn ($state): string => rtrim(rtrim(number_format((float) $state, 3, '.', ','), '0'), '.') ?: '0';

        return $table
            ->heading('Productos agotados o bajo el mínimo')
            ->query(ReplenishmentSuggestion::query()->whereIn('status', ['OUT', 'LOW'])->orderByDesc('suggested_qty'))
            ->columns([
                TextColumn::make('sku')->label('SKU'),
                TextColumn::make('product_name')->label('Producto')->wrap(),
                TextColumn::make('warehouse_name')->label('Almacén'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(static fn (string $state): string => $state === 'OUT' ? 'Agotado' : 'Bajo el mínimo')
                    ->color(static fn (string $state): string => $state === 'OUT' ? 'danger' : 'warning'),
                TextColumn::make('stock')->label('Stock')->alignEnd()->formatStateUsing($qty),
                TextColumn::make('min_qty')->label('Mínimo')->alignEnd()->formatStateUsing($qty),
                TextColumn::make('suggested_qty')->label('Pedir')->alignEnd()->weight('bold')->formatStateUsing($qty),
                TextColumn::make('supplier_name')->label('Proveedor')->placeholder('Sin proveedor'),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Todo en orden: nada por debajo del mínimo');
    }
}
