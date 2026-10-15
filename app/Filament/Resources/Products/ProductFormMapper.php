<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products;

use App\Modules\Catalog\Domain\Barcode;
use App\Modules\Catalog\Domain\BarcodeInput;
use App\Modules\Catalog\Domain\CatalogException;
use App\Modules\Catalog\Domain\Factor;
use App\Modules\Catalog\Domain\NewProduct;
use App\Modules\Catalog\Domain\PresentationInput;
use App\Modules\Catalog\Domain\ProductChanges;
use App\Modules\Catalog\Domain\StockPolicy;
use App\Modules\Catalog\Domain\Symbology;

/** Traduce los datos del formulario del panel a objetos del dominio (donde viven las reglas). */
final class ProductFormMapper
{
    /** @param array<string, mixed> $data */
    public static function toNewProduct(array $data, ?int $actorId): NewProduct
    {
        return new NewProduct(
            name: self::string($data['name'] ?? null),
            categoryId: self::requiredInt($data['category_id'] ?? null, 'la categoría'),
            baseUnitId: self::requiredInt($data['base_unit_id'] ?? null, 'la unidad base'),
            actorId: $actorId,
            description: self::nullableString($data['description'] ?? null),
            brandId: self::nullableInt($data['brand_id'] ?? null),
            referencePrice: self::price($data['reference_price'] ?? null),
            presentations: array_map(
                static fn (array $row): PresentationInput => new PresentationInput(
                    self::requiredInt($row['unit_id'] ?? null, 'la unidad de la presentación'),
                    Factor::of(self::string($row['factor'] ?? null)),
                    (bool) ($row['is_purchase_default'] ?? false),
                ),
                self::rows($data['presentations'] ?? null),
            ),
            barcodes: array_map(
                static function (array $row): BarcodeInput {
                    $symbology = Symbology::tryFrom(self::string($row['symbology'] ?? null))
                        ?? throw CatalogException::invalid('Seleccione el tipo de código de barras.');

                    return new BarcodeInput(
                        Barcode::of(self::string($row['barcode'] ?? null), $symbology),
                        self::nullableInt($row['presentation_unit_id'] ?? null),
                        (bool) ($row['is_primary'] ?? false),
                    );
                },
                self::rows($data['barcodes'] ?? null),
            ),
            policies: array_map(
                static fn (array $row): StockPolicy => new StockPolicy(
                    self::requiredInt($row['warehouse_id'] ?? null, 'el almacén de la política'),
                    self::nullableString($row['min_qty'] ?? null) ?? '0',
                    self::nullableString($row['max_qty'] ?? null),
                    self::nullableString($row['reorder_qty'] ?? null),
                ),
                self::rows($data['policies'] ?? null),
            ),
        );
    }

    /** @param array<string, mixed> $data */
    public static function toChanges(array $data, ?int $actorId): ProductChanges
    {
        return new ProductChanges(
            expectedVersion: self::requiredInt($data['version'] ?? null, 'la versión del producto'),
            actorId: $actorId,
            name: self::nullableString($data['name'] ?? null),
            description: self::nullableString($data['description'] ?? null),
            categoryId: self::nullableInt($data['category_id'] ?? null),
            brandId: self::nullableInt($data['brand_id'] ?? null),
            referencePrice: self::price($data['reference_price'] ?? null),
        );
    }

    /** @return list<array<string, mixed>> */
    private static function rows(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function nullableString(mixed $value): ?string
    {
        $s = self::string($value);

        return $s === '' ? null : $s;
    }

    private static function nullableInt(mixed $value): ?int
    {
        $s = self::string($value);

        return $s === '' ? null : (int) $s;
    }

    private static function requiredInt(mixed $value, string $label): int
    {
        return self::nullableInt($value) ?? throw CatalogException::invalid("Falta seleccionar {$label}.");
    }

    private static function price(mixed $value): ?string
    {
        $s = self::string($value);

        if ($s === '') {
            return null;
        }
        if (! is_numeric($s) || (float) $s < 0) {
            throw CatalogException::invalid('El precio referencial no es válido.');
        }

        return number_format((float) $s, 2, '.', '');
    }
}
