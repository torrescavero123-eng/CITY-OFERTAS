<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure;

use App\Modules\Operations\Domain\DocumentNumbers;
use Illuminate\Support\Facades\DB;

/** Correlativo anual atómico por prefijo (UPSERT ... RETURNING); se revierte con la transacción. */
final class DatabaseDocumentNumbers implements DocumentNumbers
{
    public function next(string $prefix): string
    {
        $year = (int) now('America/Lima')->format('Y');
        $row = DB::selectOne(
            'INSERT INTO document_counters (prefix, year, last_value) VALUES (?, ?, 1)
             ON CONFLICT (prefix, year) DO UPDATE SET last_value = document_counters.last_value + 1
             RETURNING last_value',
            [$prefix, $year],
        );

        return sprintf('%s-%d-%06d', $prefix, $year, (int) $row->last_value);
    }
}
