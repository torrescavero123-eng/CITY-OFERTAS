<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Abc\Pages;

use App\Filament\Resources\Reports\Abc\AbcResource;
use App\Filament\Support\CsvExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListAbc extends ListRecords
{
    protected static string $resource = AbcResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Descargar CSV')->icon('heroicon-o-arrow-down-tray')
                ->visible(static fn (): bool => auth()->user()?->can('reports.export') ?? false)
                ->action(static fn () => CsvExport::download(
                    'rotacion-abc',
                    ['Clase', 'SKU', 'Producto', 'Categoría', 'Vendido 90 d', 'Valor vendido', '% del total', 'Stock', 'Cobertura (días)'],
                    DB::table('v_report_abc')->orderByDesc('value_90d')->orderBy('product_name')->cursor()
                        ->map(static fn (object $r): array => [$r->abc_class, $r->sku, $r->product_name, $r->category_name, $r->qty_90d, round((float) $r->value_90d, 2), $r->share_pct, $r->stock, $r->cover_days]),
                )),
        ];
    }
}
