<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

final class CatalogException extends \DomainException
{
    /** @param array<string, scalar|null> $context */
    private function __construct(string $message, public readonly string $errorCode, public readonly array $context = [])
    {
        parent::__construct($message);
    }

    public static function invalid(string $message): self
    {
        return new self($message, 'INVALID_PRODUCT');
    }

    public static function categoryNotFound(int $id): self
    {
        return new self("La categoría {$id} no existe o está desactivada.", 'CATEGORY_NOT_FOUND', ['category_id' => $id]);
    }

    public static function brandNotFound(int $id): self
    {
        return new self("La marca {$id} no existe.", 'BRAND_NOT_FOUND', ['brand_id' => $id]);
    }

    public static function unitNotFound(int $id): self
    {
        return new self("La unidad {$id} no existe.", 'UNIT_NOT_FOUND', ['unit_id' => $id]);
    }

    public static function warehouseNotFound(int $id): self
    {
        return new self("El almacén {$id} no existe.", 'WAREHOUSE_NOT_FOUND', ['warehouse_id' => $id]);
    }

    public static function duplicateBarcode(string $barcode): self
    {
        return new self("El código de barras {$barcode} ya pertenece a otro producto.", 'DUPLICATE_BARCODE', ['barcode' => $barcode]);
    }

    public static function productNotFound(int $id): self
    {
        return new self("El producto {$id} no existe.", 'PRODUCT_NOT_FOUND', ['product_id' => $id]);
    }

    public static function staleVersion(int $expected, int $current): self
    {
        return new self(
            'El producto fue modificado por otra persona. Recargue y vuelva a intentar.',
            'STALE_VERSION',
            ['expected' => $expected, 'current' => $current],
        );
    }

    public static function hasStock(string $sku, string $quantity): self
    {
        return new self("El producto {$sku} aún tiene {$quantity} unidades en stock: no puede darse de baja.", 'HAS_STOCK', ['sku' => $sku]);
    }
}
