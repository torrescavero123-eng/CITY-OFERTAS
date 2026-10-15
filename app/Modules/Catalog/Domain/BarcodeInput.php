<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

final readonly class BarcodeInput
{
    public function __construct(
        public Barcode $barcode,
        /** Unidad de la presentación a la que pertenece el código (null = unidad base). */
        public ?int $presentationUnitId = null,
        public bool $isPrimary = false,
    ) {}

    public function asPrimary(): self
    {
        return new self($this->barcode, $this->presentationUnitId, true);
    }
}
