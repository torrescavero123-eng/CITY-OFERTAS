<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Support\Facades\DB;

/** Búsqueda en vivo para selectores de productos y ubicaciones (hay demasiados para cargarlos todos). */
final class SearchOptions
{
    /** @return array<int, string> id => "SKU — nombre" */
    public static function products(string $search): array
    {
        $s = trim($search);

        $query = DB::table('products')->whereNull('deleted_at')->where('status', 'ACTIVE');
        if ($s !== '') {
            $query->where(static function ($q) use ($s): void {
                $q->where('sku', 'ilike', "%{$s}%")
                    ->orWhere('name', 'ilike', "%{$s}%")
                    ->orWhereIn('id', DB::table('product_barcodes')->select('product_id')->where('barcode', $s));
            });
        }

        $options = [];
        foreach ($query->orderBy('name')->limit(30)->get(['id', 'sku', 'name']) as $p) {
            $options[(int) $p->id] = $p->sku.' — '.$p->name;
        }

        return $options;
    }

    public static function productLabel(mixed $id): ?string
    {
        $p = DB::table('products')->where('id', (int) $id)->first(['sku', 'name']);

        return $p === null ? null : $p->sku.' — '.$p->name;
    }

    /** @return array<int, string> id => código de ubicación */
    public static function locations(string $search): array
    {
        $s = trim($search);

        $query = DB::table('locations')->where('is_active', true);
        if ($s !== '') {
            $query->where('code', 'ilike', "%{$s}%");
        }

        $options = [];
        foreach ($query->orderBy('code')->limit(30)->get(['id', 'code']) as $l) {
            $options[(int) $l->id] = (string) $l->code;
        }

        return $options;
    }

    public static function locationLabel(mixed $id): ?string
    {
        $code = DB::table('locations')->where('id', (int) $id)->value('code');

        return $code === null ? null : (string) $code;
    }
}
