<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class InventoryStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('alerts.view') ?? false;
    }

    protected function getStats(): array
    {
        $value = (float) DB::table('stock_balances as sb')->join('products as p', 'p.id', '=', 'sb.product_id')
            ->selectRaw('COALESCE(SUM(sb.quantity * p.cost_avg), 0) AS v')->value('v');
        $out = DB::table('v_replenishment')->where('status', 'OUT')->count();
        $low = DB::table('v_replenishment')->where('status', 'LOW')->count();
        $pending = DB::table('stock_adjustment_requests')->where('status', 'PENDING')->count();
        $counting = DB::table('stock_counts')->whereIn('status', ['COUNTING', 'REVIEW'])->count();
        $waste = (float) DB::table('stock_movements')->where('type', 'MERMA')
            ->where('created_at', '>=', now('America/Lima')->startOfMonth())
            ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) AS v')->value('v');

        return [
            Stat::make('Valor del inventario', 'S/ '.number_format($value, 2, '.', ','))->description('Al costo promedio, sin IGV')->color('primary'),
            Stat::make('Agotados', (string) $out)->description('Productos con política de stock')->color($out > 0 ? 'danger' : 'success'),
            Stat::make('Bajo el mínimo', (string) $low)->description('Conviene pedir')->color($low > 0 ? 'warning' : 'success'),
            Stat::make('Ajustes por aprobar', (string) $pending)->description('Mermas y ajustes pendientes')->color($pending > 0 ? 'warning' : 'success'),
            Stat::make('Conteos abiertos', (string) $counting)->description('En curso o en revisión')->color('gray'),
            Stat::make('Mermas del mes', 'S/ '.number_format($waste, 2, '.', ','))->description('Valorizadas al costo')->color($waste > 0 ? 'warning' : 'success'),
        ];
    }
}
