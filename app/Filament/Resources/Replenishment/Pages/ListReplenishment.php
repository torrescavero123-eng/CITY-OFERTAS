<?php

declare(strict_types=1);

namespace App\Filament\Resources\Replenishment\Pages;

use App\Filament\Resources\Replenishment\ReplenishmentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListReplenishment extends ListRecords
{
    protected static string $resource = ReplenishmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Descargar pedido sugerido (CSV)')->icon('heroicon-o-arrow-down-tray')
                ->action(static fn () => response()->streamDownload(static function (): void {
                    $out = fopen('php://output', 'w');
                    if ($out === false) {
                        return;
                    }
                    fwrite($out, "\xEF\xBB\xBF"); // para que Excel abra bien los acentos
                    fputcsv($out, ['Proveedor', 'SKU', 'Producto', 'Almacén', 'Stock', 'Mínimo', 'Máximo', 'Pedir', 'Plazo (días)'], ';', '"', '');

                    $rows = DB::table('v_replenishment')->where('suggested_qty', '>', 0)->orderBy('supplier_name')->orderBy('product_name')->cursor();
                    foreach ($rows as $r) {
                        fputcsv($out, [$r->supplier_name ?? 'Sin proveedor', $r->sku, $r->product_name, $r->warehouse_name, $r->stock, $r->min_qty, $r->max_qty, $r->suggested_qty, $r->lead_time_days], ';', '"', '');
                    }
                    fclose($out);
                }, 'pedido-sugerido-'.now('America/Lima')->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
        ];
    }
}
