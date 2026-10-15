<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Descarga CSV que Excel en español abre bien (UTF-8 con BOM y punto y coma). */
final class CsvExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<scalar|null>>  $rows
     */
    public static function download(string $baseName, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(static function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';', '"', '');
            }
            fclose($out);
        }, $baseName.'-'.now('America/Lima')->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
