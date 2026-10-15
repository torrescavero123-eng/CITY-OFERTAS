<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/** Datos mínimos directos en BD (los modelos Eloquent llegan en la Parte 2). */
final class Fixtures
{
    public static function user(): int
    {
        $n = bin2hex(random_bytes(4));

        return (int) DB::table('users')->insertGetId([
            'name' => "Test {$n}", 'username' => "u{$n}", 'email' => "{$n}@test.pe", 'password' => 'x',
        ], 'id');
    }

    public static function product(string $costAvg = '0'): int
    {
        return (int) DB::table('products')->insertGetId([
            'sku' => 'TST-'.random_int(10_000_000, 99_999_999),
            'name' => 'Producto de prueba',
            'category_id' => DB::table('categories')->whereNull('parent_id')->value('id'),
            'base_unit_id' => DB::table('units')->where('code', 'UND')->value('id'),
            'cost_avg' => $costAvg,
        ], 'id');
    }

    /** @param array<string, mixed> $overrides */
    public static function location(string $warehouse = 'BOD', array $overrides = []): int
    {
        return (int) DB::table('locations')->insertGetId($overrides + [
            'warehouse_id' => DB::table('warehouses')->where('code', $warehouse)->value('id'),
            'code' => $warehouse.'-T'.bin2hex(random_bytes(4)),
        ], 'id');
    }

    public static function balance(int $productId, int $locationId): string
    {
        return (string) (DB::table('stock_balances')
            ->where('product_id', $productId)->where('location_id', $locationId)->value('quantity') ?? '0');
    }
}
