<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reports\Waste\Pages;

use App\Filament\Resources\Reports\Waste\WasteResource;
use App\Filament\Support\CsvExport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListWaste extends ListRecords
{
    protected static string $resource = WasteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Descargar CSV')->icon('heroicon-o-arrow-down-tray')
                ->visible(static fn (): bool => auth()->user()?->can('reports.export') ?? false)
                ->modalHeading('Descargar mermas y ajustes negativos')
                ->schema([
                    DatePicker::make('from')->label('Desde')->default(now('America/Lima')->startOfMonth())->required(),
                    DatePicker::make('until')->label('Hasta')->default(now('America/Lima'))->required(),
                ])
                ->action(static fn (array $data) => CsvExport::download(
                    'mermas',
                    ['Fecha', 'Tipo', 'SKU', 'Producto', 'Ubicación', 'Motivo', 'Cantidad', 'Costo unit.', 'Valor', 'Registró', 'Aprobó', 'Documento', 'Explicación'],
                    DB::table('v_report_waste')
                        ->where('created_at', '>=', substr((string) $data['from'], 0, 10).' 00:00:00-05')
                        ->where('created_at', '<=', substr((string) $data['until'], 0, 10).' 23:59:59-05')
                        ->orderBy('created_at')->cursor()
                        ->map(static fn (object $r): array => [
                            substr((string) $r->created_at, 0, 16), $r->type === 'MERMA' ? 'Merma' : 'Ajuste (-)', $r->sku, $r->product_name, $r->location_code,
                            $r->reason_name, $r->quantity, $r->unit_cost, round((float) $r->value, 2), $r->user_name, $r->approver_name, $r->document_ref, $r->notes,
                        ]),
                )),
        ];
    }
}
