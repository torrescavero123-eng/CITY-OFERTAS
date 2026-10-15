<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\ValuedStock\Pages;

use App\Filament\Resources\Reports\ValuedStock\ValuedStockResource;
use App\Filament\Support\CsvExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListValuedStock extends ListRecords
{
    protected static string $resource = ValuedStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Descargar CSV (todo el inventario)')->icon('heroicon-o-arrow-down-tray')
                ->visible(static fn (): bool => auth()->user()?->can('reports.export') ?? false)
                ->action(static fn () => CsvExport::download(
                    'inventario-valorizado',
                    ['SKU', 'Producto', 'Categoría', 'Almacén', 'Unidades', 'Costo promedio', 'Valor'],
                    DB::table('v_report_valued_stock')->orderBy('product_name')->orderBy('warehouse_name')->cursor()
                        ->map(static fn (object $r): array => [$r->sku, $r->product_name, $r->category_name, $r->warehouse_name, $r->qty, $r->cost_avg, round((float) $r->value, 2)]),
                )),
        ];
    }
}
