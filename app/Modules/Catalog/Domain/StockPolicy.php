<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

/** Política de stock de un producto en un almacén: mínimo, máximo y cantidad de reposición. */
final readonly class StockPolicy
{
    public string $minQty;

    public ?string $maxQty;

    public ?string $reorderQty;

    public function __construct(public int $warehouseId, string|int $minQty, string|int|null $maxQty = null, string|int|null $reorderQty = null)
    {
        $this->minQty = self::qty($minQty, 'mínimo', true);
        $this->maxQty = $maxQty === null ? null : self::qty($maxQty, 'máximo', true);
        $this->reorderQty = $reorderQty === null ? null : self::qty($reorderQty, 'reposición', false);

        if ($this->maxQty !== null && bccomp($this->maxQty, $this->minQty, 3) < 0) {
            throw CatalogException::invalid('El stock máximo no puede ser menor que el mínimo.');
        }
    }

    private static function qty(string|int $value, string $label, bool $allowZero): string
    {
        $raw = trim((string) $value);

        if (preg_match('/^\d{1,11}(\.\d{1,3})?$/', $raw) !== 1) {
            throw CatalogException::invalid("Cantidad inválida para {$label}: '{$raw}'.");
        }
        if (! $allowZero && bccomp($raw, '0', 3) <= 0) {
            throw CatalogException::invalid("La cantidad de {$label} debe ser mayor que cero.");
        }

        return bcadd($raw, '0', 3);
    }
}
