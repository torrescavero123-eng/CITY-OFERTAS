<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\Catalog\Domain\SkuSequence;
use Illuminate\Support\Facades\DB;

/**
 * Correlativo atómico por prefijo (UPSERT ... RETURNING). Debe ejecutarse dentro de la transacción
 * de creación: si ésta se revierte, el número no se consume.
 */
final class DatabaseSkuSequence implements SkuSequence
{
    public function next(string $categoryRootCode): string
    {
        $row = DB::selectOne(
            'INSERT INTO sku_counters (category_code, last_value) VALUES (?, 1)
             ON CONFLICT (category_code) DO UPDATE SET last_value = sku_counters.last_value + 1
             RETURNING last_value',
            [$categoryRootCode],
        );

        return sprintf('%s-%06d', $categoryRootCode, (int) $row->last_value);
    }
}
