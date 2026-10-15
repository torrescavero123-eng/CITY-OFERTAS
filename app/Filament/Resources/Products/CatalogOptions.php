<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products;

use Illuminate\Support\Facades\DB;

/** Opciones para los selectores del formulario (tablas pequeñas: se leen directo). */
final class CatalogOptions
{
    /** @return array<int, string> "Librería › Cuadernos › Rayados" (solo activas) */
    public static function categories(): array
    {
        $rows = DB::table('categories')->orderBy('depth')->orderBy('name')->get(['id', 'parent_id', 'name', 'is_active']);

        $paths = [];
        foreach ($rows as $row) {
            $parent = $row->parent_id === null ? null : ($paths[(int) $row->parent_id] ?? null);
            $paths[(int) $row->id] = $parent === null ? (string) $row->name : $parent.' › '.$row->name;
        }

        $options = [];
        foreach ($rows as $row) {
            if (filter_var($row->is_active, FILTER_VALIDATE_BOOLEAN)) {
                $options[(int) $row->id] = $paths[(int) $row->id];
            }
        }
        asort($options);

        return $options;
    }

    /** @return array<int, string> */
    public static function units(): array
    {
        $options = [];
        foreach (DB::table('units')->orderBy('name')->get(['id', 'name', 'code']) as $u) {
            $options[(int) $u->id] = $u->name.' ('.$u->code.')';
        }

        return $options;
    }

    /** @return array<int, string> */
    public static function brands(): array
    {
        $options = [];
        foreach (DB::table('brands')->orderBy('name')->get(['id', 'name']) as $b) {
            $options[(int) $b->id] = (string) $b->name;
        }

        return $options;
    }

    /** @return array<int, string> */
    public static function warehouses(): array
    {
        $options = [];
        foreach (DB::table('warehouses')->where('is_active', true)->orderBy('name')->get(['id', 'name']) as $w) {
            $options[(int) $w->id] = (string) $w->name;
        }

        return $options;
    }

    /** @return array<int, string> proveedores activos */
    public static function suppliers(): array
    {
        $options = [];
        foreach (DB::table('suppliers')->where('is_active', true)->orderBy('business_name')->get(['id', 'business_name']) as $s) {
            $options[(int) $s->id] = (string) $s->business_name;
        }

        return $options;
    }

    /**
     * Motivos de ajuste/merma del tipo indicado (AJUSTE_POSITIVO, AJUSTE_NEGATIVO o MERMA).
     *
     * @return array<int, string>
     */
    public static function reasons(string $movementType): array
    {
        $options = [];
        foreach (DB::table('movement_reasons')->where('is_active', true)->where('movement_type', $movementType)->orderBy('name')->get(['id', 'name']) as $r) {
            $options[(int) $r->id] = (string) $r->name;
        }

        return $options;
    }
}
