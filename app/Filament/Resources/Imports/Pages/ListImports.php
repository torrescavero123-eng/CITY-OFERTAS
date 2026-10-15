<?php

declare(strict_types=1);

namespace App\Filament\Resources\Imports\Pages;

use App\Filament\Resources\Imports\ImportResource;
use App\Modules\Operations\Application\ImportPosSales;
use App\Modules\Operations\Domain\OperationsException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ListImports extends ListRecords
{
    protected static string $resource = ImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->importAction('simulate', 'Simular', false, 'heroicon-o-eye', 'gray'),
            $this->importAction('commit', 'Confirmar importación', true, 'heroicon-o-check-circle', 'success'),
        ];
    }

    private function importAction(string $name, string $label, bool $commit, string $icon, string $color): Action
    {
        return Action::make($name)->label($label)->icon($icon)->color($color)
            ->visible(static fn (): bool => auth()->user()?->can('pos.import') ?? false)
            ->modalHeading($commit ? 'Confirmar importación de ventas' : 'Simular importación de ventas')
            ->modalDescription($commit
                ? 'Se descontará del piso de venta lo vendido. El mismo archivo no se puede confirmar dos veces. Las filas con error no se aplican y quedan en un reporte.'
                : 'Calcula todo SIN tocar el stock, para revisar los errores antes de confirmar.')
            ->schema([
                FileUpload::make('file')->label('Archivo CSV de la caja (columnas: sku y cantidad)')->required()
                    ->disk('local')->directory('imports/tmp')->maxSize(10240)->storeFileNamesIn('original_name'),
                DatePicker::make('business_date')->label('Fecha de las ventas')->required()->default(now('America/Lima')),
            ])
            ->action(static function (array $data) use ($commit): void {
                $path = (string) ($data['file'] ?? '');

                try {
                    $csv = Storage::disk('local')->get($path) ?? '';
                    $csv = mb_check_encoding($csv, 'UTF-8') ? $csv : (string) mb_convert_encoding($csv, 'UTF-8', 'ISO-8859-1'); // Excel en Windows

                    $result = app(ImportPosSales::class)(
                        $csv,
                        (string) ($data['original_name'] ?? basename($path)),
                        substr((string) ($data['business_date'] ?? ''), 0, 10),
                        (int) auth()->id(),
                        $commit,
                    );

                    if ($result->errors !== []) {
                        $report = 'imports/errors/pos-'.$result->importId.'.csv';
                        Storage::disk('local')->put($report, $result->errorReportCsv());
                        DB::table('import_jobs')->where('id', $result->importId)->update(['error_report_path' => $report]);
                    }

                    Notification::make()
                        ->title(($commit ? 'Importación confirmada' : 'Simulación lista').': '.$result->okRows.' producto(s)')
                        ->body("Filas leídas: {$result->totalRows}. Unidades: {$result->units}. Con error: ".count($result->errors).($result->errors !== [] ? '. Descargue el reporte desde la lista.' : '.'))
                        ->color($result->errors === [] ? 'success' : 'warning')->persistent()->send();
                } catch (OperationsException $e) {
                    Notification::make()->title('No se pudo importar')->body($e->getMessage())->danger()->send();
                } finally {
                    if ($path !== '') {
                        Storage::disk('local')->delete($path);
                    }
                }
            });
    }
}
