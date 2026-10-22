<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

final readonly class NewProduct
{
    public string $name;

    public ?string $description;

    /** @var list<PresentationInput> la unidad base siempre va primero con factor 1 */
    public array $presentations;

    /** @var list<BarcodeInput> exactamente uno es principal (si hay códigos) */
    public array $barcodes;

    /** @var list<StockPolicy> */
    public array $policies;

    /**
     * @param  list<PresentationInput>  $presentations
     * @param  list<BarcodeInput>  $barcodes
     * @param  list<StockPolicy>  $policies
     */
    public function __construct(
        string $name,
        public int $categoryId,
        public int $baseUnitId,
        public ?int $actorId = null,
        ?string $description = null,
        public ?int $brandId = null,
        public ?string $referencePrice = null,
        array $presentations = [],
        array $barcodes = [],
        array $policies = [],
    ) {
        $this->name = self::normalizeName($name);
        $this->description = self::normalizeDescription($description);

        if ($referencePrice !== null && preg_match('/^\d{1,10}(\.\d{1,2})?$/', $referencePrice) !== 1) {
            throw CatalogException::invalid('El precio referencial no es válido (máximo 2 decimales).');
        }

        $this->presentations = self::normalizePresentations($baseUnitId, $presentations);
        $this->barcodes = self::normalizeBarcodes($barcodes, $this->presentations);
        $this->policies = self::normalizePolicies($policies);
    }

    public static function normalizeName(string $name): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', $name));

        if (mb_strlen($clean) < 2 || mb_strlen($clean) > 200) {
            throw CatalogException::invalid('El nombre debe tener entre 2 y 200 caracteres.');
        }

        return $clean;
    }

    public static function normalizeDescription(?string $description): ?string
    {
        if ($description === null || trim($description) === '') {
            return null;
        }
        if (mb_strlen($description) > 2000) {
            throw CatalogException::invalid('La descripción no puede superar 2000 caracteres.');
        }

        return trim($description);
    }

    /**
     * @param  list<PresentationInput>  $input
     * @return list<PresentationInput>
     */
    private static function normalizePresentations(int $baseUnitId, array $input): array
    {
        $base = new PresentationInput($baseUnitId, Factor::of(1));
        $seen = [$baseUnitId => true];
        $out = [$base];
        $defaults = 0;

        foreach ($input as $p) {
            if ($p->unitId === $baseUnitId) {
                if (! $p->factor->isOne()) {
                    throw CatalogException::invalid('La presentación de la unidad base debe tener factor 1.');
                }
                $out[0] = $p;
            } else {
                if (isset($seen[$p->unitId])) {
                    throw CatalogException::invalid('Hay presentaciones repetidas para la misma unidad.');
                }
                $seen[$p->unitId] = true;
                $out[] = $p;
            }
            $defaults += $p->isPurchaseDefault ? 1 : 0;
        }

        if ($defaults > 1) {
            throw CatalogException::invalid('Solo una presentación puede ser la de compra por defecto.');
        }

        return $out;
    }

    /**
     * @param  list<BarcodeInput>  $barcodes
     * @param  list<PresentationInput>  $presentations
     * @return list<BarcodeInput>
     */
    private static function normalizeBarcodes(array $barcodes, array $presentations): array
    {
        if ($barcodes === []) {
            return [];
        }

        $units = array_map(static fn (PresentationInput $p): int => $p->unitId, $presentations);
        $seen = [];
        $primaries = 0;

        foreach ($barcodes as $b) {
            if (isset($seen[$b->barcode->value])) {
                throw CatalogException::invalid("El código {$b->barcode->value} está repetido en el formulario.");
            }
            $seen[$b->barcode->value] = true;

            if ($b->presentationUnitId !== null && ! in_array($b->presentationUnitId, $units, true)) {
                throw CatalogException::invalid('Un código de barras referencia una presentación que no existe.');
            }
            $primaries += $b->isPrimary ? 1 : 0;
        }

        if ($primaries > 1) {
            throw CatalogException::invalid('Solo un código puede ser el principal.');
        }
        if ($primaries === 0) {
            $barcodes[0] = $barcodes[0]->asPrimary();
        }

        return $barcodes;
    }

    /**
     * @param  list<StockPolicy>  $policies
     * @return list<StockPolicy>
     */
    private static function normalizePolicies(array $policies): array
    {
        $seen = [];
        foreach ($policies as $p) {
            if (isset($seen[$p->warehouseId])) {
                throw CatalogException::invalid('Hay más de una política de stock para el mismo almacén.');
            }
            $seen[$p->warehouseId] = true;
        }

        return $policies;
    }
}
