<?php

declare(strict_types=1);

namespace App\Modules\Operations\Domain;

use App\Modules\Inventory\Domain\Quantity;
use App\Modules\Inventory\Domain\UnitCost;

/** Línea de recepción SIEMPRE en unidad base (cantidad y costo por unidad base, sin IGV). */
final readonly class ReceiptLine
{
    public function __construct(
        public int $productId,
        public int $locationId,
        public Quantity $quantity,
        public UnitCost $unitCost,
        public ?Quantity $expected = null,
        public ?int $presentationId = null,
        public ?Quantity $presentationQty = null,
    ) {
        if (($presentationId === null) !== ($presentationQty === null)) {
            throw OperationsException::invalid('La presentación y su cantidad deben informarse juntas.');
        }
    }

    /** Convierte una recepción por caja/docena a unidad base: cantidad x factor; costo / factor. */
    public static function fromPresentation(
        int $productId,
        int $locationId,
        int $presentationId,
        Quantity $presentationQty,
        string $factor,
        string $costPerPresentation,
        ?Quantity $expectedPresentation = null,
    ): self {
        if (preg_match('/^\d{1,9}(\.\d{1,3})?$/', $factor) !== 1 || bccomp($factor, '0', 3) <= 0) {
            throw OperationsException::invalid('El factor de la presentación no es válido.');
        }

        // Costo por unidad base con redondeo half-up a 4 decimales.
        $costBase = bcadd(bcdiv($costPerPresentation, $factor, 8), '0.00005', 4);

        return new self(
            $productId,
            $locationId,
            Quantity::of(bcmul($presentationQty->value, $factor, 3)),
            UnitCost::of($costBase),
            $expectedPresentation === null ? null : Quantity::of(bcmul($expectedPresentation->value, $factor, 3)),
            $presentationId,
            $presentationQty,
        );
    }

    public function hasDiscrepancy(): bool
    {
        return $this->expected !== null && ! $this->quantity->equals($this->expected);
    }
}
