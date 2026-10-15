<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

/** Cambios parciales (null = no cambiar). El SKU y la unidad base nunca cambian. */
final readonly class ProductChanges
{
    public ?string $name;

    public ?string $description;

    public function __construct(
        public int $expectedVersion,
        public ?int $actorId = null,
        ?string $name = null,
        ?string $description = null,
        public ?int $categoryId = null,
        public ?int $brandId = null,
        public ?string $referencePrice = null,
    ) {
        $this->name = $name === null ? null : NewProduct::normalizeName($name);
        $this->description = $description === null ? null : NewProduct::normalizeDescription($description);

        if ($referencePrice !== null && preg_match('/^\d{1,10}(\.\d{1,2})?$/', $referencePrice) !== 1) {
            throw CatalogException::invalid('El precio referencial no es válido (máximo 2 decimales).');
        }
    }

    /** @return array<string, scalar> campos con valor nuevo */
    public function fields(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'brand_id' => $this->brandId,
            'reference_price' => $this->referencePrice,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
